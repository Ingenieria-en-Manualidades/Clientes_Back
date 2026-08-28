<?php

namespace App\Http\Controllers;

use App\Models\DetailedAssembly;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class DetailedAssemblyController extends Controller
{
    private const SHEET_NAME = 'Hoja5';

    private const REQUIRED_COLUMNS = [
        'nombre centro' => 'Nombre 1',
        'sku' => 'Material',
        'producto' => 'Texto breve de material',
        'total semana' => 'Total',
    ];

    private const CLIENT_NAME_ALIASES = [
        'cedi madrid' => 'gaseosas lux madrid',
        'cedi calle 80' => 'gaseosas lux calle 80',
        'calle 80' => 'gaseosas lux calle 80',
        'calle 80 exportacion' => 'gaseosas lux calle 80',
        'postobon malambo exportacion' => 'postobon malambo',
        'lux sur' => 'gaslux sur',
        'gaseosas lux sur' => 'gaslux sur',
    ];

    public function __construct(private readonly DetailedAssembly $detailedAssembly) {}

    public function previewExcel(Request $request): JsonResponse
    {
        $requestData = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'week' => ['required', 'integer', 'between:1,53'],
            'username' => ['required', 'string', 'max:255'],
        ]);

        $year = (int) $requestData['year'];
        $week = (int) $requestData['week'];
        $weeksInYear = Carbon::create($year, 12, 28)->isoWeek();

        if ($week > $weeksInYear) {
            return response()->json([
                'success' => false,
                'message' => "El año {$year} solo tiene {$weeksInYear} semanas ISO.",
            ], 422);
        }

        $startDate = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();
        $spreadsheet = null;

        try {
            $filePath = $request->file('archivo')->getPathname();
            $reader = IOFactory::createReaderForFile($filePath);
            $worksheetName = collect($reader->listWorksheetNames($filePath))
                ->first(fn (string $sheetName) => $this->normalizeHeader($sheetName)
                    === $this->normalizeHeader(self::SHEET_NAME));

            if ($worksheetName === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo debe contener la hoja "'.self::SHEET_NAME.'".',
                ], 422);
            }

            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$worksheetName]);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheetByName($worksheetName);

            if ($worksheet === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fue posible cargar la hoja "'.self::SHEET_NAME.'".',
                ], 422);
            }

            $rows = $worksheet->toArray(null, true, false, false);
            $headerRowIndex = null;
            $columnIndexes = [];

            foreach ($rows as $index => $row) {
                $candidateColumnIndexes = $this->resolveColumnIndexes($row);

                if (count($candidateColumnIndexes) > count($columnIndexes)) {
                    $columnIndexes = $candidateColumnIndexes;
                }

                if (count($candidateColumnIndexes) === count(self::REQUIRED_COLUMNS)) {
                    $headerRowIndex = $index;
                    $columnIndexes = $candidateColumnIndexes;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                $missingColumns = array_values(array_diff(
                    array_keys(self::REQUIRED_COLUMNS),
                    array_keys($columnIndexes)
                ));

                return response()->json([
                    'success' => false,
                    'message' => 'La hoja no contiene todas las columnas requeridas.',
                    'missing_columns' => array_map(
                        fn (string $column) => self::REQUIRED_COLUMNS[$column],
                        $missingColumns
                    ),
                ], 422);
            }

            $values = [];
            $validationErrors = [];
            $preparedRows = [];

            foreach (array_slice($rows, $headerRowIndex + 1) as $index => $row) {
                $excelRow = $headerRowIndex + $index + 2;
                $rowValues = [
                    'nombre centro' => $this->normalizeStringValue(
                        $row[$columnIndexes['nombre centro']] ?? null
                    ),
                    'sku' => $this->normalizeStringValue(
                        $row[$columnIndexes['sku']] ?? null
                    ),
                    'producto' => $this->normalizeStringValue(
                        $row[$columnIndexes['producto']] ?? null
                    ),
                    'total semana' => $row[$columnIndexes['total semana']] ?? null,
                ];

                if ($this->isEmptyRow($rowValues)) {
                    continue;
                }

                if ($this->isSummaryRow($rowValues)) {
                    continue;
                }

                if ($this->isZeroWeeklyTotal($rowValues['total semana'])) {
                    continue;
                }

                $validator = Validator::make($rowValues, [
                    'nombre centro' => ['required', 'string'],
                    'sku' => ['required', 'string'],
                    'producto' => ['required', 'string'],
                    'total semana' => ['required', 'integer'],
                ]);

                if ($validator->fails()) {
                    $validationErrors[$excelRow] = $validator->errors()->toArray();

                    continue;
                }

                $validated = $validator->validated();
                $preparedRows[] = [
                    'excel_row' => $excelRow,
                    'nombre_cliente' => $validated['nombre centro'],
                    'sku' => trim($validated['sku']),
                    'producto' => trim($validated['producto']),
                    'value' => (int) $validated['total semana'],
                ];
            }

            $connection = DB::connection($this->detailedAssembly->getConnectionName());
            $clientIdsByNormalizedName = $this->resolveClientIds(
                $connection,
                array_column($preparedRows, 'nombre_cliente')
            );
            $rowsWithClient = [];

            foreach ($preparedRows as $preparedRow) {
                $clientId = $clientIdsByNormalizedName[
                    $this->normalizeClientName($preparedRow['nombre_cliente'])
                ] ?? null;

                if ($clientId === null) {
                    $validationErrors[$preparedRow['excel_row']] = [
                        'nombre centro' => [
                            'No se encontró un cliente para "'.$preparedRow['nombre_cliente'].'".',
                        ],
                    ];

                    continue;
                }

                $preparedRow['client_id'] = (int) $clientId;
                $rowsWithClient[] = $preparedRow;
            }

            // Búsqueda y validación de activity_id.
            $activitiesByClientAndSku = $this->findActivitiesByClientAndSku(
                $connection,
                $rowsWithClient
            );
            
            foreach ($rowsWithClient as $rowWithClient) {
                $activityId = $this->resolveActivityId(
                    $activitiesByClientAndSku,
                    $rowWithClient['client_id'],
                    $rowWithClient['sku'],
                    $rowWithClient['producto']
                );
            
                if ($activityId === null) {
                    $validationErrors[$rowWithClient['excel_row']] = [
                        'activity_id' => [
                            'No se encontró una actividad para el cliente "'
                            .$rowWithClient['nombre_cliente'].'", SKU "'
                            .$rowWithClient['sku'].'" y producto "'
                            .$rowWithClient['producto'].'".',
                        ],
                    ];
            
                    continue;
                }
            
                $values[] = [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'nombre_cliente' => $rowWithClient['nombre_cliente'],
                    'client_id' => $rowWithClient['client_id'],
                    'sku' => $rowWithClient['sku'],
                    'producto' => $rowWithClient['producto'],
                    'activity_id' => $activityId,
                    'value' => $rowWithClient['value'],
                ];
            }

            foreach ($rowsWithClient as $rowWithClient) {
                $values[] = [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'nombre_cliente' => $rowWithClient['nombre_cliente'],
                    'client_id' => $rowWithClient['client_id'],
                    'sku' => $rowWithClient['sku'],
                    'producto' => $rowWithClient['producto'],
                    'activity_id' => '',
                    'value' => $rowWithClient['value'],
                ];
            }

            if ($validationErrors !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hay filas con valores inválidos en la hoja.',
                    'errors' => $validationErrors,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'El archivo fue leído correctamente.',
                'data' => [
                    'year' => $year,
                    'week' => $week,
                    'username' => $requestData['username'],
                    'values' => $values,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('No fue posible leer el archivo de armado detallado', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible leer el archivo Excel.',
            ], 422);
        } finally {
            $spreadsheet?->disconnectWorksheets();
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'week' => ['required', 'integer', 'between:1,53'],
            'username' => ['required', 'string', 'max:255'],
            'replace_existing' => ['sometimes', 'boolean'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.client_id' => ['required', 'integer'],
            'values.*.sku' => ['required', 'string', 'max:255'],
            'values.*.producto' => ['required', 'string', 'max:255'],
            'values.*.value' => ['required', 'integer'],
        ]);

        $year = (int) $validated['year'];
        $week = (int) $validated['week'];
        $weeksInYear = Carbon::create($year, 12, 28)->isoWeek();

        if ($week > $weeksInYear) {
            return response()->json([
                'success' => false,
                'message' => "El año {$year} solo tiene {$weeksInYear} semanas ISO.",
            ], 422);
        }

        $duplicateDetailKeys = collect($validated['values'])
            ->map(
                static fn (array $value) => $value['client_id'].'|'.$value['sku'].'|'.$value['producto']
            )
            ->duplicates();

        if ($duplicateDetailKeys->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No puede haber detalles repetidos para el mismo cliente, SKU y producto.',
            ], 422);
        }

        $startDate = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();
        $replaceExisting = (bool) ($validated['replace_existing'] ?? false);

        try {
            $result = DB::connection($this->detailedAssembly->getConnectionName())
                ->transaction(function () use (
                    $validated,
                    $year,
                    $week,
                    $startDate,
                    $endDate,
                    $replaceExisting
                ) {
                    $detailedAssembly = $this->detailedAssembly->newQuery()
                        ->where('year', $year)
                        ->where('week_number', $week)
                        ->where('username', $validated['username'])
                        ->lockForUpdate()
                        ->first();

                    if ($detailedAssembly !== null && ! $replaceExisting) {
                        return [
                            'requires_confirmation' => true,
                            'detailed_assembly' => $detailedAssembly,
                        ];
                    }

                    $action = 'updated';

                    if ($detailedAssembly === null) {
                        $detailedAssembly = $this->detailedAssembly->newQuery()->create([
                            'year' => $year,
                            'week_number' => $week,
                            'week_start_date' => $startDate->toDateString(),
                            'week_end_date' => $endDate->toDateString(),
                            'notes' => null,
                            'username' => $validated['username'],
                        ]);
                        $action = 'created';
                    } else {
                        $detailedAssembly->weekly_detailed_assemblies()->delete();
                    }

                    $detailedAssembly->weekly_detailed_assemblies()->createMany(
                        $this->buildWeeklyDetailedAssemblies(
                            $validated['values'],
                            $validated['username']
                        )
                    );

                    return [
                        'requires_confirmation' => false,
                        'action' => $action,
                        'detailed_assembly' => $detailedAssembly,
                        'details_count' => count($validated['values']),
                    ];
                });

            if ($result['requires_confirmation']) {
                $detailedAssembly = $result['detailed_assembly'];

                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => 'Ya existe un armado detallado para este usuario, año y semana.',
                    'data' => [
                        'detailed_assembly_id' => $detailedAssembly->getKey(),
                        'year' => $detailedAssembly->year,
                        'week' => $detailedAssembly->week_number,
                        'username' => $detailedAssembly->username,
                    ],
                ], 409);
            }

            $status = $result['action'] === 'created' ? 201 : 200;

            return response()->json([
                'success' => true,
                'message' => $result['action'] === 'created'
                    ? 'El armado detallado fue guardado correctamente.'
                    : 'El armado detallado fue actualizado correctamente.',
                'data' => [
                    'detailed_assembly_id' => $result['detailed_assembly']->getKey(),
                    'details_count' => $result['details_count'],
                ],
            ], $status);
        } catch (Throwable $exception) {
            Log::error('No fue posible guardar el armado detallado', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible guardar el armado detallado.',
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $records = $this->detailedAssembly->newQuery()
                ->select([
                    'detailed_assembly_id',
                    'year',
                    'week_number',
                    'week_start_date',
                    'week_end_date',
                    'username',
                ])
                ->with(['weekly_detailed_assemblies' => function ($query) {
                    $query
                        ->leftJoin(
                            'public.cliente as c',
                            'c.cliente_id',
                            '=',
                            'weekly_detailed_assembly.client_id'
                        )
                        ->select([
                            'weekly_detailed_assembly.weekly_detailed_assembly_id',
                            'weekly_detailed_assembly.detailed_assembly_id',
                            'weekly_detailed_assembly.sku',
                            'weekly_detailed_assembly.product',
                            'weekly_detailed_assembly.weekly_total',
                            'weekly_detailed_assembly.username',
                            'c.nombre as client_name',
                        ]);
                }])
                ->orderByDesc('week_start_date')
                ->orderByDesc('detailed_assembly_id')
                ->get()
                ->map(static function (DetailedAssembly $detailedAssembly): array {
                    return [
                        'detailed_assembly_id' => $detailedAssembly->getKey(),
                        'year' => $detailedAssembly->year,
                        'week_number' => $detailedAssembly->week_number,
                        'week_start_date' => $detailedAssembly->week_start_date,
                        'week_end_date' => $detailedAssembly->week_end_date,
                        'username' => $detailedAssembly->username,
                        'weekly_detailed_assembly' => $detailedAssembly->weekly_detailed_assemblies
                            ->map(static fn ($weeklyDetailedAssembly): array => [
                                'weekly_detailed_assembly_id' => $weeklyDetailedAssembly->getKey(),
                                'client_name' => $weeklyDetailedAssembly->client_name,
                                'sku' => $weeklyDetailedAssembly->sku,
                                'product' => $weeklyDetailedAssembly->product,
                                'weekly_total' => $weeklyDetailedAssembly->weekly_total,
                                'username' => $weeklyDetailedAssembly->username,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Armado detallado consultado correctamente.',
                'data' => $records,
            ]);
        } catch (Throwable $exception) {
            Log::error('No fue posible listar el armado detallado', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible listar el armado detallado.',
            ], 500);
        }
    }

    private function buildWeeklyDetailedAssemblies(array $values, string $username): array
    {
        return array_map(
            static fn (array $value) => [
                'client_id' => (int) $value['client_id'],
                'weekly_total' => (int) $value['value'],
                'notes' => null,
                'sku' => $value['sku'],
                'product' => $value['producto'],
                'username' => $username,
            ],
            $values
        );
    }

    private function resolveColumnIndexes(array $headers): array
    {
        $requiredByNormalizedName = [];

        foreach (self::REQUIRED_COLUMNS as $attribute => $column) {
            $requiredByNormalizedName[$this->normalizeHeader($column)] = $attribute;
        }

        $columnIndexes = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeHeader((string) $header);

            if (isset($requiredByNormalizedName[$normalizedHeader])) {
                $columnIndexes[$requiredByNormalizedName[$normalizedHeader]] = $index;
            }
        }

        return $columnIndexes;
    }

    private function normalizeHeader(string $header): string
    {
        $normalizedHeader = preg_replace('/\s+/', ' ', $header) ?? $header;

        return mb_strtolower(trim(preg_replace('/\s*:\s*/', ':', $normalizedHeader) ?? $normalizedHeader));
    }

    private function resolveClientIds($connection, array $clientNames): array
    {
        if ($clientNames === []) {
            return [];
        }

        $clients = $connection
            ->table('public.cliente as c')
            ->select(['c.cliente_id', 'c.nombre'])
            ->get()
            ->map(fn ($client) => [
                'client_id' => (int) $client->cliente_id,
                'normalized_name' => $this->normalizeClientName((string) $client->nombre),
            ])
            ->all();
        $clientIdsByExactName = [];

        foreach ($clients as $client) {
            if (! isset($clientIdsByExactName[$client['normalized_name']])) {
                $clientIdsByExactName[$client['normalized_name']] = $client['client_id'];
            }
        }

        $clientIdsByNormalizedName = [];

        foreach (array_unique($clientNames) as $clientName) {
            $normalizedClientName = $this->normalizeClientName($clientName);
            $clientId = $clientIdsByExactName[$normalizedClientName] ?? null;

            if ($clientId === null) {
                $alias = self::CLIENT_NAME_ALIASES[$normalizedClientName] ?? null;
                $clientId = $alias === null
                    ? null
                    : ($clientIdsByExactName[$alias] ?? null);
            }

            if ($clientId === null) {
                $matchingClientIds = collect($clients)
                    ->filter(
                        static fn (array $client) => str_contains(
                            $client['normalized_name'],
                            $normalizedClientName
                        )
                    )
                    ->pluck('client_id');

                $clientId = $matchingClientIds->count() === 1
                    ? $matchingClientIds->first()
                    : null;
            }

            $clientIdsByNormalizedName[$normalizedClientName] = $clientId;
        }

        return $clientIdsByNormalizedName;
    }

    private function findActivitiesByClientAndSku($connection, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $clientIds = array_values(array_unique(array_column($rows, 'client_id')));
        $skus = array_values(array_unique(array_column($rows, 'sku')));
        $activitiesByClientAndSku = [];

        $activities = $connection
            ->table('public.actividad as a')
            ->select([
                'a.actividad_id',
                'a.cliente_id',
                'a.codigo_cobro',
                'a.nombre',
            ])
            ->whereIn('a.cliente_id', $clientIds)
            ->whereIn('a.codigo_cobro', $skus)
            ->whereNull('a.deleted_at')
            ->orderBy('a.actividad_id')
            ->get();

        foreach ($activities as $activity) {
            $activityKey = $this->activityKey(
                (int) $activity->cliente_id,
                (string) $activity->codigo_cobro
            );
            $activitiesByClientAndSku[$activityKey][] = [
                'activity_id' => $activity->actividad_id,
                'nombre' => (string) $activity->nombre,
            ];
        }

        return $activitiesByClientAndSku;
    }

    private function resolveActivityId(
        array $activitiesByClientAndSku,
        int $clientId,
        string $sku,
        string $producto
    ): mixed {
        $candidates = $activitiesByClientAndSku[
            $this->activityKey($clientId, $sku)
        ] ?? [];

        foreach ($candidates as $activity) {
            if (str_ends_with($activity['nombre'], $producto)) {
                return $activity['activity_id'];
            }
        }

        return $candidates[0]['activity_id'] ?? null;
    }

    private function activityKey(int $clientId, string $sku): string
    {
        return $clientId.'|'.$sku;
    }

    private function normalizeClientName(string $clientName): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $clientName) ?? $clientName));
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(
            static fn ($value) => $value === null || trim((string) $value) === ''
        );
    }

    private function isZeroWeeklyTotal(mixed $value): bool
    {
        if (is_string($value) && trim($value) === '-') {
            return true;
        }

        return is_numeric($value) && (float) $value === 0.0;
    }

    private function isSummaryRow(array $row): bool
    {
        return collect([
            $row['nombre centro'] ?? null,
            $row['sku'] ?? null,
            $row['producto'] ?? null,
        ])->every(
            static fn ($value) => $value === null || trim((string) $value) === ''
        );
    }

    private function normalizeStringValue(mixed $value): mixed
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : $value;
    }
}
