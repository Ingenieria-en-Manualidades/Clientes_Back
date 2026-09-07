<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PulseMetricsController extends Controller
{
    private array $modules = [
        'Usuarios',
        'Roles',
        'Clientes',
        'Metas',
        'Cumplimiento Mensual',
        'Unidades programadas',
        'Cumplimiento Diarios',
        'Encuesta',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->query('days', 30), 90));
        $from = now()->subDays($days - 1)->toDateString();
        $base = DB::table('metric_usages')
            ->where('date', '>=', $from)
            ->whereIn('module', $this->modules);

        $summary = (clone $base)
            ->selectRaw('count(distinct user_id) as active_users')
            ->selectRaw('coalesce(sum(requests_count), 0) as total_requests')
            ->selectRaw('coalesce(sum(sessions_count), 0) as started_sessions')
            ->selectRaw('coalesce(sum(errors_count), 0) as total_errors')
            ->selectRaw('coalesce(sum(slow_requests_count), 0) as slow_requests')
            ->selectRaw('coalesce(sum(critical_actions_count), 0) as critical_actions')
            ->first();

        $modules = (clone $base)
            ->select('module')
            ->selectRaw('sum(requests_count) as requests')
            ->selectRaw('count(distinct user_id) as users_count')
            ->selectRaw('sum(errors_count) as errors')
            ->selectRaw('sum(slow_requests_count) as slow_requests')
            ->groupBy('module')
            ->orderByDesc('requests')
            ->get();

        $totalModuleRequests = (int) $modules->sum('requests');
        $requestsByModule = $modules->pluck('requests', 'module');
        $lowUsageModules = collect($this->modules)
            ->map(function (string $module) use ($requestsByModule, $totalModuleRequests, $modules) {
                $requests = (int) ($requestsByModule->get($module) ?? 0);
                $moduleSummary = $modules->firstWhere('module', $module);

                return [
                    'module' => $module,
                    'requests' => $requests,
                    'users_count' => (int) ($moduleSummary->users_count ?? 0),
                    'percentage' => $totalModuleRequests > 0 ? round(($requests / $totalModuleRequests) * 100, 2) : 0,
                ];
            })
            ->sortBy('requests')
            ->values();

        $users = (clone $base)
            ->join('users', 'users.id', '=', 'metric_usages.user_id')
            ->select('users.id', 'users.name')
            ->selectRaw('sum(metric_usages.requests_count) as requests')
            ->selectRaw('max(metric_usages.last_activity_at) as last_activity_at')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $lastActivity = (clone $base)
            ->join('users', 'users.id', '=', 'metric_usages.user_id')
            ->select('users.id', 'users.name')
            ->selectRaw('max(metric_usages.last_activity_at) as last_activity_at')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('last_activity_at')
            ->limit(10)
            ->get();

        $userModuleTotals = (clone $base)
            ->join('users', 'users.id', '=', 'metric_usages.user_id')
            ->leftJoin('clientes', 'clientes.id', '=', 'metric_usages.cliente_id')
            ->select('users.id as user_id', 'users.name', 'metric_usages.module')
            ->selectRaw("coalesce(clientes.nombre, 'Sin cliente') as client")
            ->selectRaw('sum(metric_usages.requests_count) as requests')
            ->groupBy('users.id', 'users.name', 'clientes.nombre', 'metric_usages.module')
            ->get();

        $requestsByUser = $userModuleTotals
            ->groupBy('user_id')
            ->map(fn ($items) => (int) $items->sum('requests'));

        $userModuleUsage = $userModuleTotals
            ->map(function ($item) use ($requestsByUser) {
                $totalRequests = $requestsByUser->get($item->user_id, 0);

                return [
                    'user_id' => $item->user_id,
                    'name' => $item->name,
                    'client' => $item->client,
                    'module' => $item->module,
                    'requests' => (int) $item->requests,
                    'percentage' => $totalRequests > 0 ? round(((int) $item->requests / $totalRequests) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('percentage')
            ->values();

        $roles = (clone $base)
            ->leftJoin('roles', 'roles.id', '=', 'metric_usages.role_id')
            ->selectRaw("coalesce(roles.name, 'Sin rol') as role")
            ->selectRaw('sum(metric_usages.requests_count) as requests')
            ->groupBy('roles.name')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $clients = (clone $base)
            ->leftJoin('clientes', 'clientes.id', '=', 'metric_usages.cliente_id')
            ->selectRaw("coalesce(clientes.nombre, 'Sin cliente') as client")
            ->selectRaw('round(avg(metric_usages.requests_count), 2) as average_usage')
            ->selectRaw('sum(metric_usages.requests_count) as requests')
            ->groupBy('clientes.nombre')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $hours = (clone $base)
            ->select('hour')
            ->selectRaw('sum(requests_count) as requests')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $daily = (clone $base)
            ->select('date')
            ->selectRaw('sum(requests_count) as requests')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'modules' => $modules,
                'most_active_users' => $users,
                'last_activity_by_user' => $lastActivity,
                'user_module_usage' => $userModuleUsage,
                'roles' => $roles,
                'clients' => $clients,
                'peak_hours' => $hours,
                'daily_usage' => $daily,
                'low_usage_modules' => $lowUsageModules,
            ],
        ]);
    }

}
