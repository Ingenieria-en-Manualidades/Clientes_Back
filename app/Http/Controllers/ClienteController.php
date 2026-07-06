<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    private function tableExists(string $qualifiedTable): bool
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::selectOne('SELECT to_regclass(?) AS table_name', [$qualifiedTable])->table_name !== null;
        }

        if (DB::connection()->getDriverName() === 'sqlite' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
                return false;
            }

            return DB::selectOne("SELECT name FROM {$schema}.sqlite_master WHERE type = 'table' AND name = ?", [$table]) !== null;
        }

        return DB::getSchemaBuilder()->hasTable($qualifiedTable);
    }

    private function firstExistingTable(array $qualifiedTables): ?string
    {
        foreach ($qualifiedTables as $qualifiedTable) {
            if ($this->tableExists($qualifiedTable)) {
                return $qualifiedTable;
            }
        }

        return null;
    }

    private function tableColumns(string $qualifiedTable): array
    {
        if (DB::connection()->getDriverName() === 'pgsql' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);

            return DB::table('information_schema.columns')
                ->where('table_schema', $schema)
                ->where('table_name', $table)
                ->pluck('column_name')
                ->all();
        }

        if (DB::connection()->getDriverName() === 'sqlite' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);
            if (
                !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)
                || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)
            ) {
                return [];
            }

            return collect(DB::select("PRAGMA {$schema}.table_info({$table})"))
                ->pluck('name')
                ->all();
        }

        return DB::getSchemaBuilder()->getColumnListing($qualifiedTable);
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function rowValue(object $row, ?string $column, mixed $default = null): mixed
    {
        return $column && property_exists($row, $column) ? $row->{$column} : $default;
    }

    private function isInactiveValue(mixed $value): bool
    {
        return $value !== null && strtolower(trim((string) $value)) !== 's';
    }

    private function syncClientesSequence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('clientes', 'id'),
                COALESCE((SELECT MAX(id) FROM clientes), 0) + 1,
                false
            )
        ");
    }

    private function syncSurveyClientsSequence(string $surveyTable, string $primaryKey): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (!$this->tableExists($surveyTable)) {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence(?, ?),
                COALESCE((SELECT MAX({$primaryKey}) FROM {$surveyTable}), 0) + 1,
                false
            )
        ", [$surveyTable, $primaryKey]);
    }

    // get all clients.
    public function getClients()
    {
        try {
            $clients = Cliente::withTrashed()->orderBy('nombre','asc')->get();

            if ($clients->isEmpty()) {
                return response()->json(['title' => 'Clientes no encontrados.', 'message' => 'Sin clientes existentes.'], 404);
            } else {
                return response()->json(['data' => $clients], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function getClientsByIds($arrayIds)
    {
        try {
            $ids = explode(',', $arrayIds);

            $clients = Cliente::select('cliente_endpoint_id', 'nombre')
            ->whereIn('cliente_endpoint_id', $ids)
            ->orderBy('nombre','asc')
            ->get();

            if ($clients->isEmpty()) {
                return response()->json(['title' => 'Clientes no encontrados.', 'message' => 'Usuario no relacionado con ningun cliente.'], 404);
            } else {
                return response()->json(['data' => $clients], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Por favor recargar la página.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUsersByClientId(int $id)
    {
        try {
            $client = Cliente::findOrFail($id);
            $hasSurveyContacts = $this->tableExists('surveys.customer_contact');
            $fullNameExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "TRIM(COALESCE(e.nombre, '') || ' ' || COALESCE(e.apellido, '')) as fullname"
                : "TRIM(CONCAT(COALESCE(e.nombre, ''), ' ', COALESCE(e.apellido, ''))) as fullname";

            $users = DB::table('cliente_user as cu')
                ->join('users as u', 'u.id', '=', 'cu.user_id')
                ->leftJoin('public.empleado as e', 'e.empleado_id', '=', 'u.empleado_id');

            if ($hasSurveyContacts) {
                $users->leftJoin('surveys.customer_contact as cc', function ($join) {
                    $join->on('cc.user_id', '=', 'u.id')
                        ->whereNull('cc.deleted_at');
                });
            }

            $selects = [
                    'u.id',
                    'u.name as username',
                    'u.email',
                    'u.activo',
                    'u.deleted_at',
                    DB::raw($fullNameExpression),
            ];

            $selects[] = $hasSurveyContacts ? 'cc.cellphone' : DB::raw('NULL as cellphone');
            $selects[] = $hasSurveyContacts ? 'cc.fullname as contact_fullname' : DB::raw('NULL as contact_fullname');

            $users = $users->select($selects)
                ->where('cu.cliente_id', $client->id)
                ->whereNull('cu.deleted_at')
                ->orderBy('u.name', 'asc')
                ->get();

            if ($users->isEmpty()) {
                return response()->json(['title' => 'Usuarios no encontrados.', 'message' => 'Este cliente no tiene usuarios asociados.'], 404);
            }

            return response()->json(['data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function createClient(Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));
            $expectedToken = config('app.api_key_app_clients');

            if ($token !== $expectedToken) {
                return response()->json(['title' => 'Token no valido.', 'message' => 'Error en la peticion al enviar el token incorrecto.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'cliente_endpoint_id' => 'required|integer|unique:clientes,cliente_endpoint_id',
            ]);

            if ($validator->fails()) {
                return response()->json(['title' => 'Error de validacion.', 'message' => $validator->errors(), 'error' => $validator->errors()], 422);
            }

            $this->syncClientesSequence();

            $client = new Cliente();
            $client->nombre = $request->nombre;
            $client->cliente_endpoint_id = $request->cliente_endpoint_id;
            $client->activo = $request->activo ?? 's';
            $client->save();

            return response()->json(['title' => 'Exito.', 'message' => 'Cliente creado exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function syncClients(Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));
            $expectedToken = config('app.api_key_app_clients');

            if ($token !== $expectedToken) {
                return response()->json(['title' => 'Token no valido.', 'message' => 'Error en la peticion al enviar el token incorrecto.'], 401);
            }

            $publicClientTable = $this->firstExistingTable(['public.cliente']);

            if (!$publicClientTable) {
                return response()->json([
                    'title' => 'Fuente no disponible.',
                    'message' => 'No existe la tabla cliente dentro del esquema public para sincronizar clientes.',
                ], 409);
            }

            $surveyClientTable = $this->firstExistingTable(['surveys.clients']);
            $hasSurveyClients = $surveyClientTable !== null;
            $hasPublicActiveClients = $this->tableExists('public.clientes_activos');

            $publicColumns = $this->tableColumns($publicClientTable);
            $publicClientIdColumn = $this->firstExistingColumn($publicColumns, ['cliente_id', 'clients_id', 'id']);
            $publicClientNameColumn = $this->firstExistingColumn($publicColumns, ['nombre', 'name']);

            if (!$publicClientIdColumn || !$publicClientNameColumn) {
                return response()->json([
                    'title' => 'Fuente no compatible.',
                    'message' => "La tabla {$publicClientTable} no tiene columnas de id/nombre compatibles para sincronizar.",
                ], 409);
            }

            $publicClients = DB::table($publicClientTable)->get();

            $surveyColumns = $hasSurveyClients ? $this->tableColumns($surveyClientTable) : [];
            $surveyPrimaryKey = $this->firstExistingColumn($surveyColumns, ['clients_id', 'cliente_id', 'id']);
            $surveyNameColumn = $this->firstExistingColumn($surveyColumns, ['name', 'nombre']);

            $surveyClientIds = [];
            if ($hasSurveyClients && $hasPublicActiveClients) {
                $surveyClientIds = DB::table('public.clientes_activos')
                    ->pluck('cliente_id')
                    ->map(fn ($clientId) => (int) $clientId)
                    ->flip()
                    ->all();
            }

            $createdClients = 0;
            $updatedClients = 0;
            $createdSurveyClients = 0;
            $updatedSurveyClients = 0;
            $skippedSurveyClients = 0;

            DB::transaction(function () use ($publicClients, $publicClientIdColumn, $publicClientNameColumn, $surveyClientIds, $surveyClientTable, $surveyColumns, $surveyPrimaryKey, $surveyNameColumn, $hasSurveyClients, $hasPublicActiveClients, &$createdClients, &$updatedClients, &$createdSurveyClients, &$updatedSurveyClients, &$skippedSurveyClients) {
                $this->syncClientesSequence();

                foreach ($publicClients as $publicClient) {
                    $publicClientId = $this->rowValue($publicClient, $publicClientIdColumn);
                    $publicClientName = $this->rowValue($publicClient, $publicClientNameColumn);

                    if (!$publicClientId || !$publicClientName) {
                        continue;
                    }

                    $sourceDeletedAt = $this->rowValue($publicClient, 'deleted_at');
                    $sourceInactive = $sourceDeletedAt !== null || $this->isInactiveValue($this->rowValue($publicClient, 'activo', 's'));
                    $syncedDeletedAt = $sourceInactive ? ($sourceDeletedAt ?? now()) : null;

                    $client = Cliente::withTrashed()->firstOrNew([
                        'cliente_endpoint_id' => $publicClientId,
                    ]);
                    $client->nombre = $publicClientName;
                    $client->activo = $sourceInactive ? 'n' : ($this->rowValue($publicClient, 'activo', 's') ?? 's');
                    $client->deleted_at = $syncedDeletedAt;
                    $client->exists ? $updatedClients++ : $createdClients++;
                    $client->save();
                    if (!$sourceInactive && method_exists($client, 'trashed') && $client->trashed()) {
                        $client->restore();
                    }

                    if (!$hasSurveyClients) {
                        $skippedSurveyClients++;
                        continue;
                    }

                    if (!$surveyPrimaryKey || !$surveyNameColumn) {
                        $skippedSurveyClients++;
                        continue;
                    }

                    $surveyClient = DB::table($surveyClientTable)
                        ->where($surveyPrimaryKey, $publicClientId)
                        ->first();

                    if (!$surveyClient) {
                        $surveyClient = DB::table($surveyClientTable)
                            ->whereRaw("LOWER(TRIM({$surveyNameColumn})) = LOWER(TRIM(?))", [$publicClientName])
                            ->first();
                    }

                    if (
                        !$hasPublicActiveClients
                        || (!$surveyClient && !$sourceInactive && !array_key_exists((int) $publicClientId, $surveyClientIds))
                    ) {
                        $skippedSurveyClients++;
                        continue;
                    }

                    $surveyData = [];
                    if (
                        !$surveyClient
                        || (
                            (int) $surveyClient->{$surveyPrimaryKey} !== (int) $publicClientId
                            && !DB::table($surveyClientTable)->where($surveyPrimaryKey, $publicClientId)->exists()
                        )
                    ) {
                        $surveyData[$surveyPrimaryKey] = $publicClientId;
                    }
                    $surveyData[$surveyNameColumn] = $publicClientName;
                    if (in_array('feed_value', $surveyColumns, true)) {
                        $surveyData['feed_value'] = $this->rowValue($publicClient, 'valor_alimentacion', 0) ?? 0;
                    }
                    if (in_array('cost_center', $surveyColumns, true)) {
                        $surveyData['cost_center'] = $this->rowValue($publicClient, 'centro_costo', '') ?? '';
                    }
                    if (in_array('overtime', $surveyColumns, true)) {
                        $surveyData['overtime'] = $this->rowValue($publicClient, 'hora_extra', '00:00:00') ?? '00:00:00';
                    }
                    if (in_array('username', $surveyColumns, true)) {
                        $surveyData['username'] = $this->rowValue($publicClient, 'usuario', 'sync') ?? 'sync';
                    }
                    if (in_array('city_id', $surveyColumns, true)) {
                        $surveyData['city_id'] = $this->rowValue($publicClient, 'ciudad_id', 0) ?? 0;
                    }
                    if (in_array('cost_center_type', $surveyColumns, true)) {
                        $surveyData['cost_center_type'] = $this->rowValue($publicClient, 'tipo_centro_costo', '') ?? '';
                    }
                    if (in_array('company_id', $surveyColumns, true)) {
                        $surveyData['company_id'] = $this->rowValue($publicClient, 'empresa_id', 0) ?? 0;
                    }
                    if (in_array('purchasing_manager_id', $surveyColumns, true)) {
                        $surveyData['purchasing_manager_id'] = $this->rowValue($publicClient, 'responsable_compras');
                    }
                    if (in_array('client_cg1_id', $surveyColumns, true)) {
                        $surveyData['client_cg1_id'] = $this->rowValue($publicClient, 'cliente_cg1_id');
                    }
                    if (in_array('active', $surveyColumns, true)) {
                        $surveyData['active'] = !$sourceInactive;
                    }
                    if (in_array('activo', $surveyColumns, true)) {
                        $surveyData['activo'] = $sourceInactive ? 'n' : ($this->rowValue($publicClient, 'activo', 's') ?? 's');
                    }
                    if (in_array('updated_at', $surveyColumns, true)) {
                        $surveyData['updated_at'] = now();
                    }
                    if (in_array('deleted_at', $surveyColumns, true)) {
                        $surveyData['deleted_at'] = $syncedDeletedAt;
                    }

                    if ($surveyClient) {
                        DB::table($surveyClientTable)
                            ->where($surveyPrimaryKey, $surveyClient->{$surveyPrimaryKey})
                            ->update($surveyData);
                        $updatedSurveyClients++;
                    } else {
                        if (in_array('created_at', $surveyColumns, true)) {
                            $surveyData['created_at'] = now();
                        }
                        DB::table($surveyClientTable)->insert($surveyData);
                        $createdSurveyClients++;
                    }
                }

                $this->syncClientesSequence();
                if ($hasSurveyClients && $surveyPrimaryKey) {
                    $this->syncSurveyClientsSequence($surveyClientTable, $surveyPrimaryKey);
                }
            });

            $surveyMessage = $hasSurveyClients
                ? "Surveys ({$surveyClientTable}): {$createdSurveyClients} creados, {$updatedSurveyClients} actualizados y {$skippedSurveyClients} omitidos."
                : "Surveys omitido: no existe la tabla clients dentro del esquema surveys en esta base de datos.";

            return response()->json([
                'title' => 'Sincronizacion exitosa.',
                'message' => "Clientes sincronizados: {$createdClients} creados y {$updatedClients} actualizados. {$surveyMessage}",
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function updateClient(int $id, Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));
            $expectedToken = config('app.api_key_app_clients');

            if ($token !== $expectedToken) {
                return response()->json(['title' => 'Token no valido.', 'message' => 'Error en la peticion al enviar el token incorrecto.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'cliente_endpoint_id' => [
                    'required',
                    'integer',
                    Rule::unique('clientes', 'cliente_endpoint_id')->ignore($id),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['title' => 'Error de validacion.', 'message' => $validator->errors(), 'error' => $validator->errors()], 422);
            }

            $client = Cliente::findOrFail($id);
            $client->nombre = $request->nombre;
            $client->cliente_endpoint_id = $request->cliente_endpoint_id;
            $client->save();

            return response()->json(['title' => 'Exito.', 'message' => 'Cliente actualizado exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function setStatusClient(int $id, Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));
            $expectedToken = config('app.api_key_app_clients');

            if ($token !== $expectedToken) {
                return response()->json(['title' => 'Token no valido.', 'message' => 'Error en la peticion al enviar el token incorrecto.'], 401);
            }

            $client = Cliente::withTrashed()->findOrFail($id);
            $isDisabled = method_exists($client, 'trashed') && $client->trashed();

            if ($isDisabled) {
                $client->restore();
                $client->activo = 's';
                $client->save();
                $deletedAt = null;
                $active = true;
                $message = 'Cliente habilitado exitosamente.';
            } else {
                $client->activo = 'n';
                $client->save();
                $client->delete();
                $deletedAt = $client->deleted_at ?? now();
                $active = false;
                $message = 'Cliente deshabilitado exitosamente.';
            }

            $surveyClientTable = $this->firstExistingTable(['surveys.clients']);
            if ($surveyClientTable) {
                $surveyColumns = $this->tableColumns($surveyClientTable);
                $surveyPrimaryKey = $this->firstExistingColumn($surveyColumns, ['clients_id', 'cliente_id', 'id']);
                $surveyNameColumn = $this->firstExistingColumn($surveyColumns, ['name', 'nombre']);

                if ($surveyPrimaryKey || $surveyNameColumn) {
                    $surveyData = [];
                    if (in_array('deleted_at', $surveyColumns, true)) {
                        $surveyData['deleted_at'] = $deletedAt;
                    }
                    if (in_array('active', $surveyColumns, true)) {
                        $surveyData['active'] = $active;
                    }
                    if (in_array('activo', $surveyColumns, true)) {
                        $surveyData['activo'] = $active ? 's' : 'n';
                    }
                    if (in_array('updated_at', $surveyColumns, true)) {
                        $surveyData['updated_at'] = now();
                    }

                    if ($surveyData) {
                        $surveyQuery = DB::table($surveyClientTable);
                        if ($surveyPrimaryKey) {
                            $surveyQuery->where($surveyPrimaryKey, $client->cliente_endpoint_id);
                        } elseif ($surveyNameColumn) {
                            $surveyQuery->whereRaw("LOWER(TRIM({$surveyNameColumn})) = LOWER(TRIM(?))", [$client->nombre]);
                        }
                        $surveyQuery->update($surveyData);
                    }
                }
            }

            return response()->json(['title' => 'Exito.', 'message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }
}
