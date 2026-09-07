<?php

namespace App\Http\Controllers\Admon;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    private const METRIC_TIMEZONE = 'America/Bogota';

    /**
     * Muestra el dashboard con los contadores de clientes activos, usuarios activos y roles.
     *
     * @desc Este método obtiene los contadores de clientes activos, usuarios activos y roles de la base de datos y los pasa a la vista del dashboard.
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Contar los clientes activos
        $activeClients = Cliente::all()->count();
        // Contar los usuarios activos
        $activeUsers = User::all()->count();
        // Contar los roles
        $rolesCount = Role::count();
        // Retornar la vista del dashboard con los datos contados
        return view('dashboard', compact('activeClients', 'activeUsers', 'rolesCount'));
    }

    public function metrics(Request $request)
    {
        try {
            $days = (int) $request->query('days', 30);
            $days = max(1, min($days, 365));
            $fromDate = Carbon::now(self::METRIC_TIMEZONE)->subDays($days - 1)->startOfDay();

            if (! Schema::hasTable('metric_usages')) {
                return response()->json([
                    'data' => [
                        'summary' => [
                            'active_users' => 0,
                            'active_clients' => 0,
                            'started_sessions' => 0,
                            'total_requests' => 0,
                            'total_errors' => 0,
                            'slow_requests' => 0,
                            'critical_actions' => 0,
                        ],
                        'modules' => [],
                        'most_active_users' => [],
                        'last_activity_by_user' => [],
                        'user_module_usage' => [],
                        'roles' => [],
                        'clients' => [],
                        'peak_hours' => [],
                        'daily_usage' => [],
                        'low_usage_modules' => [],
                    ],
                ], 200);
            }

            $normalizedModule = "case when mu.submodule = 'Tablero Sae' and mu.module in ('Metas', 'Cumplimiento Mensual', 'Unidades programadas', 'Cumplimiento Diarios') then 'Tablero Sae' when mu.submodule = 'Administracion' and mu.module in ('Usuarios', 'Roles', 'Clientes', 'Politicas') then 'Administracion' else mu.module end";

            $base = DB::table('metric_usages as mu')
                ->join('users as u', 'u.id', '=', 'mu.user_id')
                ->where('mu.date', '>=', $fromDate->toDateString())
                ->where('mu.module', '!=', 'Autenticacion')
                ->where('u.activo', 's')
                ->whereNull('u.deleted_at');

            $summary = (clone $base)
                ->selectRaw('count(distinct mu.user_id) as active_users')
                ->selectRaw('count(distinct mu.cliente_id) as active_clients')
                ->selectRaw('coalesce(sum(mu.sessions_count), 0) as started_sessions')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as total_requests')
                ->selectRaw('coalesce(sum(mu.errors_count), 0) as total_errors')
                ->selectRaw('coalesce(sum(mu.slow_requests_count), 0) as slow_requests')
                ->selectRaw('coalesce(sum(mu.critical_actions_count), 0) as critical_actions')
                ->first();

            $totalRequests = (int) ($summary->total_requests ?? 0);

            $modules = (clone $base)
                ->selectRaw($normalizedModule.' as module')
                ->selectRaw('count(distinct mu.user_id) as users_count')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->selectRaw('coalesce(sum(mu.errors_count), 0) as errors')
                ->selectRaw('coalesce(sum(mu.slow_requests_count), 0) as slow_requests')
                ->selectRaw('coalesce(sum(mu.critical_actions_count), 0) as critical_actions')
                ->groupByRaw($normalizedModule)
                ->orderByDesc('requests')
                ->get()
                ->map(fn ($module) => [
                    'module' => $module->module,
                    'users_count' => (int) $module->users_count,
                    'requests' => (int) $module->requests,
                    'errors' => (int) $module->errors,
                    'slow_requests' => (int) $module->slow_requests,
                    'critical_actions' => (int) $module->critical_actions,
                    'percentage' => $totalRequests > 0 ? round(((int) $module->requests / $totalRequests) * 100, 2) : 0,
                ]);

            $lowUsageModules = $modules
                ->sortBy([
                    ['percentage', 'asc'],
                    ['requests', 'asc'],
                ])
                ->values();

            $mostActiveUsers = (clone $base)
                ->select('u.name as user')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('requests')
                ->take(10)
                ->get()
                ->map(fn ($user) => [
                    'user' => $user->user,
                    'requests' => (int) $user->requests,
                ]);

            $lastActivityByUser = (clone $base)
                ->select('u.name as user')
                ->selectRaw('max(mu.last_activity_at) as last_activity')
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('last_activity')
                ->take(10)
                ->get();

            $userModuleUsage = (clone $base)
                ->select('u.name as user')
                ->selectRaw($normalizedModule.' as module')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->groupBy('u.id', 'u.name')
                ->groupByRaw($normalizedModule)
                ->orderBy('u.name')
                ->orderBy('module')
                ->get()
                ->map(fn ($row) => [
                    'user' => $row->user,
                    'module' => $row->module,
                    'user_module' => $row->user.' - '.$row->module,
                    'requests' => (int) $row->requests,
                ]);

            $dailyUsage = (clone $base)
                ->select('mu.date')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->groupBy('mu.date')
                ->orderBy('mu.date')
                ->get()
                ->map(fn ($day) => [
                    'date' => $day->date,
                    'requests' => (int) $day->requests,
                ]);

            $peakHours = (clone $base)
                ->select('mu.hour')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->groupBy('mu.hour')
                ->orderByDesc('requests')
                ->take(8)
                ->get()
                ->map(fn ($hour) => [
                    'hour' => str_pad((string) $hour->hour, 2, '0', STR_PAD_LEFT),
                    'requests' => (int) $hour->requests,
                ]);

            $roles = (clone $base)
                ->leftJoin('roles as r', 'r.id', '=', 'mu.role_id')
                ->selectRaw("coalesce(r.name, 'Sin rol') as role")
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->groupBy('r.id', 'r.name')
                ->orderByDesc('requests')
                ->take(10)
                ->get()
                ->map(fn ($role) => [
                    'role' => $role->role,
                    'requests' => (int) $role->requests,
                ]);

            $clients = (clone $base)
                ->leftJoin('clientes as c', 'c.id', '=', 'mu.cliente_id')
                ->selectRaw("coalesce(c.nombre, 'Sin cliente') as client")
                ->selectRaw('count(distinct mu.user_id) as users_count')
                ->selectRaw('coalesce(sum(mu.requests_count), 0) as requests')
                ->selectRaw('case when count(distinct mu.user_id) = 0 then 0 else round(sum(mu.requests_count)::numeric / count(distinct mu.user_id), 1) end as average_usage')
                ->groupBy('c.id', 'c.nombre')
                ->orderByDesc('requests')
                ->take(10)
                ->get()
                ->map(fn ($client) => [
                    'client' => $client->client,
                    'users_count' => (int) $client->users_count,
                    'requests' => (int) $client->requests,
                    'average_usage' => (float) $client->average_usage,
                ]);

            return response()->json([
                'data' => [
                    'summary' => [
                        'active_users' => (int) ($summary->active_users ?? 0),
                        'active_clients' => (int) ($summary->active_clients ?? 0),
                        'started_sessions' => (int) ($summary->started_sessions ?? 0),
                        'total_requests' => $totalRequests,
                        'total_errors' => (int) ($summary->total_errors ?? 0),
                        'slow_requests' => (int) ($summary->slow_requests ?? 0),
                        'critical_actions' => (int) ($summary->critical_actions ?? 0),
                    ],
                    'modules' => $modules,
                    'most_active_users' => $mostActiveUsers,
                    'last_activity_by_user' => $lastActivityByUser,
                    'user_module_usage' => $userModuleUsage,
                    'roles' => $roles,
                    'clients' => $clients,
                    'peak_hours' => $peakHours,
                    'daily_usage' => $dailyUsage,
                    'low_usage_modules' => $lowUsageModules,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'title' => 'Error con el servidor.',
                'message' => 'Ha ocurrido un fallo al consultar las metricas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function trackModuleAccess(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|max:100',
            'route' => 'nullable|string|max:255',
        ]);

        $clientEndpointId = (int) $request->header('X-Cliente-Id');
        $clientId = $clientEndpointId > 0
            ? Cliente::query()
                ->join('cliente_user as cu', 'cu.cliente_id', '=', 'clientes.id')
                ->where('clientes.cliente_endpoint_id', $clientEndpointId)
                ->where('cu.user_id', $request->user()->id)
                ->whereNull('clientes.deleted_at')
                ->whereNull('cu.deleted_at')
                ->value('clientes.id')
            : null;

        $logData = [
            'user_id' => $request->user()->id,
            'module' => $validated['module'],
            'route' => $validated['route'] ?? null,
            'accessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('module_access_logs', 'cliente_id')) {
            $logData['cliente_id'] = $clientId;
        }

        DB::table('module_access_logs')->insert($logData);

        return response()->json(['success' => true], 201);
    }

    public function monthlyMetrics(Request $request)
    {
        try {
            if (!Schema::hasTable('metric_usages')) {
                $fromDate = Carbon::now(self::METRIC_TIMEZONE)->startOfMonth();
                $toDate = Carbon::now(self::METRIC_TIMEZONE)->endOfDay();

                return response()->json([
                    'data' => [
                        'month' => $fromDate->format('Y-m'),
                        'from' => $fromDate->toDateString(),
                        'to' => $toDate->toDateString(),
                        'total_accesses' => 0,
                        'summary' => [
                            'clients_count' => 0,
                            'modules_count' => 0,
                            'users_count' => 0,
                            'top_client' => null,
                            'top_module' => null,
                        ],
                        'clients' => [],
                        'modules' => [],
                        'rows' => [],
                    ],
                ], 200);
            }

            $from = $request->query('from', Carbon::now(self::METRIC_TIMEZONE)->startOfMonth()->toDateString());
            $to = $request->query('to', Carbon::now(self::METRIC_TIMEZONE)->toDateString());

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                return response()->json([
                    'title' => 'Rango invalido.',
                    'message' => 'Las fechas deben tener el formato YYYY-MM-DD.',
                ], 422);
            }

            $fromDate = Carbon::createFromFormat('Y-m-d', $from, self::METRIC_TIMEZONE)->startOfDay();
            $toDate = Carbon::createFromFormat('Y-m-d', $to, self::METRIC_TIMEZONE)->endOfDay();

            if ($fromDate->gt($toDate)) {
                return response()->json([
                    'title' => 'Rango invalido.',
                    'message' => 'La fecha desde no puede ser mayor que la fecha hasta.',
                ], 422);
            }

            $normalizedModule = "case when mu.submodule = 'Tablero Sae' and mu.module in ('Metas', 'Cumplimiento Mensual', 'Unidades programadas', 'Cumplimiento Diarios') then 'Tablero Sae' when mu.submodule = 'Administracion' and mu.module in ('Usuarios', 'Roles', 'Clientes', 'Politicas') then 'Administracion' else mu.module end";
            $normalizedSubmodule = "case when mu.submodule = 'Tablero Sae' and mu.module in ('Metas', 'Cumplimiento Mensual', 'Unidades programadas', 'Cumplimiento Diarios') then mu.module when mu.submodule = 'Administracion' and mu.module in ('Usuarios', 'Roles', 'Clientes', 'Politicas') then mu.module else mu.submodule end";

            if (Schema::hasTable('metric_usage_events')) {
                $logs = DB::table('metric_usage_events as mu')
                    ->join('users as u', 'u.id', '=', 'mu.user_id')
                    ->leftJoin('public.empleado as e', 'e.empleado_id', '=', 'u.empleado_id')
                    ->leftJoin('clientes as c', 'c.id', '=', 'mu.cliente_id')
                    ->whereBetween('mu.date', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->where('mu.module', '!=', 'Autenticacion')
                    ->where('u.activo', 's')
                    ->whereNull('u.deleted_at')
                    ->select(
                        'e.empleado_id as user_id',
                        'u.name as user',
                        DB::raw("coalesce(nullif(trim(concat(coalesce(e.nombre, ''), ' ', coalesce(e.apellido, ''))), ''), u.name) as user_name"),
                        'e.nro_documento as document_number',
                        DB::raw("coalesce(c.nombre, 'Sin cliente') as client"),
                        DB::raw($normalizedModule.' as module'),
                        DB::raw($normalizedSubmodule.' as submodule'),
                        'mu.action',
                        'mu.method',
                        'mu.route',
                        'mu.fecha_registro',
                        DB::raw('1 as accesses')
                    )
                    ->orderByDesc('mu.fecha_registro')
                    ->orderBy('u.name')
                    ->get();
            } else {
                $logs = DB::table('metric_usages as mu')
                    ->join('users as u', 'u.id', '=', 'mu.user_id')
                    ->leftJoin('public.empleado as e', 'e.empleado_id', '=', 'u.empleado_id')
                    ->leftJoin('clientes as c', 'c.id', '=', 'mu.cliente_id')
                    ->whereBetween('mu.date', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->where('mu.module', '!=', 'Autenticacion')
                    ->where('u.activo', 's')
                    ->whereNull('u.deleted_at')
                    ->select(
                        'e.empleado_id as user_id',
                        'u.name as user',
                        DB::raw("coalesce(nullif(trim(concat(coalesce(e.nombre, ''), ' ', coalesce(e.apellido, ''))), ''), u.name) as user_name"),
                        'e.nro_documento as document_number',
                        DB::raw("coalesce(c.nombre, 'Sin cliente') as client"),
                        DB::raw($normalizedModule.' as module'),
                        DB::raw($normalizedSubmodule.' as submodule'),
                        DB::raw("string_agg(distinct mu.action, ', ' order by mu.action) as action"),
                        DB::raw("string_agg(distinct mu.method, ', ' order by mu.method) as method"),
                        DB::raw("string_agg(distinct mu.route, ', ' order by mu.route) as route"),
                        DB::raw('sum(mu.requests_count) as accesses'),
                        DB::raw('max(mu.last_activity_at) as fecha_registro')
                    )
                    ->groupBy('e.empleado_id', 'e.nombre', 'e.apellido', 'e.nro_documento', 'u.name', 'c.id', 'c.nombre')
                    ->groupByRaw($normalizedModule)
                    ->groupByRaw($normalizedSubmodule)
                    ->orderByDesc('accesses')
                    ->orderBy('u.name')
                    ->get();
            }

            $totalAccesses = $logs->sum('accesses');

            $rows = $logs->map(fn ($row) => [
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'user' => $row->user,
                'user_name' => $row->user_name,
                'document_number' => $row->document_number,
                'client' => $row->client,
                'module' => $row->module,
                'submodule' => $row->submodule,
                'action' => $row->action,
                'method' => $row->method,
                'route' => $row->route,
                'fecha_registro' => $row->fecha_registro,
                'accesses' => (int) $row->accesses,
                'percentage' => $totalAccesses > 0 ? round(((int) $row->accesses / $totalAccesses) * 100, 2) : 0,
            ]);

            $clients = $rows
                ->groupBy('client')
                ->map(function ($clientRows, $client) use ($totalAccesses) {
                    $clientAccesses = $clientRows->sum('accesses');
                    $modules = $clientRows
                        ->groupBy('module')
                        ->map(function ($moduleRows, $module) use ($clientAccesses) {
                            $moduleAccesses = $moduleRows->sum('accesses');
                            $users = $moduleRows
                                ->groupBy('user_id')
                                ->map(function ($userRows) use ($moduleAccesses) {
                                    $firstRow = $userRows->first();
                                    $userAccesses = $userRows->sum('accesses');

                                    return [
                                        'user_id' => $firstRow['user_id'],
                                        'user' => $firstRow['user'],
                                        'user_name' => $firstRow['user_name'],
                                        'accesses' => $userAccesses,
                                        'percentage' => $moduleAccesses > 0 ? round(($userAccesses / $moduleAccesses) * 100, 2) : 0,
                                    ];
                                })
                                ->sortByDesc('accesses')
                                ->values();

                            return [
                                'module' => $module,
                                'users_count' => $moduleRows->pluck('user_id')->unique()->count(),
                                'accesses' => $moduleAccesses,
                                'percentage' => $clientAccesses > 0 ? round(($moduleAccesses / $clientAccesses) * 100, 2) : 0,
                                'users' => $users,
                            ];
                        })
                        ->sortByDesc('accesses')
                        ->values();

                    return [
                        'client' => $client,
                        'users_count' => $clientRows->pluck('user_id')->unique()->count(),
                        'modules_count' => $modules->count(),
                        'accesses' => $clientAccesses,
                        'percentage' => $totalAccesses > 0 ? round(($clientAccesses / $totalAccesses) * 100, 2) : 0,
                        'modules' => $modules,
                    ];
                })
                ->sortByDesc('accesses')
                ->values();

            $modules = $rows
                ->groupBy('module')
                ->map(function ($items, $module) use ($totalAccesses) {
                    $accesses = $items->sum('accesses');

                    return [
                        'module' => $module,
                        'users_count' => $items->pluck('user_id')->unique()->count(),
                        'accesses' => $accesses,
                        'percentage' => $totalAccesses > 0 ? round(($accesses / $totalAccesses) * 100, 2) : 0,
                    ];
                })
                ->sortByDesc('accesses')
                ->values();

            $topClient = $clients->first();
            $topModule = $modules->first();

            $summary = [
                'clients_count' => $clients->count(),
                'modules_count' => $modules->count(),
                'users_count' => $rows->pluck('user_id')->unique()->count(),
                'top_client' => $topClient['client'] ?? null,
                'top_module' => $topModule['module'] ?? null,
            ];

            return response()->json([
                'data' => [
                    'month' => $fromDate->format('Y-m'),
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                    'total_accesses' => $totalAccesses,
                    'summary' => $summary,
                    'clients' => $clients,
                    'modules' => $modules,
                    'rows' => $rows,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'title' => 'Error con el servidor.',
                'message' => 'Ha ocurrido un fallo al consultar las metricas mensuales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
