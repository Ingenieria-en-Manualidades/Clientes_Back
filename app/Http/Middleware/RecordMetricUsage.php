<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordMetricUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        if ($this->shouldRecord($request)) {
            try {
                $this->record($request, $response, $startedAt);
            } catch (\Throwable $exception) {
                Log::warning('Unable to record usage metrics.', [
                    'message' => $exception->getMessage(),
                    'path' => $request->path(),
                    'user_id' => $request->user()?->id,
                ]);
            }
        }

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        $metricRoute = $this->metricRoute('/'.$request->path());

        return $request->is('api/*')
            && ! $request->is('api/metrics/*')
            && $metricRoute['module'] !== 'Autenticacion'
            && $metricRoute['module'] !== 'Sin clasificar'
            && $this->isReportableActivity($metricRoute['action'])
            && $this->metricUser($request) !== null;
    }

    private function record(Request $request, Response $response, float $startedAt): void
    {
        $user = $this->metricUser($request);

        if (! $user) {
            return;
        }

        $now = now();
        $route = '/'.$request->path();
        $role = $user->roles()->select('roles.id')->first();
        $clienteId = $this->clienteId($request, $user->id);
        $duration = (microtime(true) - $startedAt) * 1000;
        $metricRoute = $this->metricRoute($route);

        $attributes = [
            'date' => $now->toDateString(),
            'hour' => $now->hour,
            'user_id' => $user->id,
            'cliente_id' => $clienteId,
            'role_id' => $role?->id,
            'module' => $metricRoute['module'],
            'submodule' => $metricRoute['submodule'],
            'method' => $request->method(),
            'route' => $this->normalizeRoute($route),
        ];

        DB::table('metric_usages')->upsert([
            $attributes + [
                'requests_count' => 1,
                'errors_count' => $response->getStatusCode() >= 400 ? 1 : 0,
                'slow_requests_count' => $duration >= 1000 ? 1 : 0,
                'sessions_count' => $request->is('api/login') ? 1 : 0,
                'critical_actions_count' => $this->isCriticalAction($request) ? 1 : 0,
                'action' => $metricRoute['action'],
                'last_activity_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['date', 'hour', 'user_id', 'cliente_id', 'role_id', 'module', 'submodule', 'method', 'route'], [
            'requests_count' => DB::raw('metric_usages.requests_count + 1'),
            'errors_count' => DB::raw('metric_usages.errors_count + '.($response->getStatusCode() >= 400 ? 1 : 0)),
            'slow_requests_count' => DB::raw('metric_usages.slow_requests_count + '.($duration >= 1000 ? 1 : 0)),
            'sessions_count' => DB::raw('metric_usages.sessions_count + '.($request->is('api/login') ? 1 : 0)),
            'critical_actions_count' => DB::raw('metric_usages.critical_actions_count + '.($this->isCriticalAction($request) ? 1 : 0)),
            'action' => $metricRoute['action'],
            'last_activity_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function clienteId(Request $request, int $userId): ?int
    {
        $clientId = $request->header('X-Cliente-Id');

        if ($clientId) {
            return DB::table('clientes')->where('cliente_endpoint_id', (int) $clientId)->value('id')
                ?? (int) $clientId;
        }

        $clientId = $request->input('cliente_id')
            ?? $request->input('clientes_id')
            ?? $request->route('client_id')
            ?? $request->route('clienteID')
            ?? $request->route('clients_id');

        if ($clientId) {
            return (int) $clientId;
        }

        $query = DB::table('cliente_user')->where('user_id', $userId);

        if (Schema::hasColumn('cliente_user', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('cliente_id')->value('cliente_id');
    }

    private function normalizeRoute(string $route): string
    {
        return preg_replace('#/\d+(?=/|$)#', '/{id}', $route) ?? $route;
    }

    private function metricRoute(string $route): array
    {
        $normalizedRoute = $this->normalizeRoute($route);

        $routes = [
            '/api/login' => ['Autenticacion', 'Inicio de sesion', 'Iniciar sesion'],
            '/api/logout' => ['Autenticacion', 'Cierre de sesion', 'Cerrar sesion'],
            '/api/verificarTokenLogin' => ['Autenticacion', 'Validacion de sesion', 'Validar token'],
            '/api/updatePasswordExpiration' => ['Autenticacion', 'Contrasena', 'Actualizar contrasena'],
            '/api/policy' => ['Administracion', 'Politicas', 'Ver politica'],
            '/api/policy/status' => ['Administracion', 'Politicas', 'Consultar estado de politica'],
            '/api/policy/accept' => ['Administracion', 'Politicas', 'Aceptar politica'],
            '/api/getUsers' => ['Administracion', 'Usuarios', 'Listar usuarios'],
            '/api/getRoles' => ['Administracion', 'Usuarios', 'Listar roles de usuario'],
            '/api/getDataUserId/{id}' => ['Administracion', 'Usuarios', 'Consultar usuario'],
            '/api/getEmployeesImec/{id}' => ['Administracion', 'Usuarios', 'Listar empleados IMEC'],
            '/api/createUser' => ['Administracion', 'Usuarios', 'Crear usuario'],
            '/api/updateUser/{id}' => ['Administracion', 'Usuarios', 'Actualizar usuario'],
            '/api/setStatusUser/{id}' => ['Administracion', 'Usuarios', 'Cambiar estado de usuario'],
            '/api/resetUser/{id}' => ['Administracion', 'Usuarios', 'Restablecer usuario'],
            '/api/getListPermissions' => ['Administracion', 'Roles', 'Listar permisos'],
            '/api/getAdminRoles' => ['Administracion', 'Roles', 'Listar roles'],
            '/api/getDisabledAdminRoles' => ['Administracion', 'Roles', 'Listar roles inhabilitados'],
            '/api/createRole' => ['Administracion', 'Roles', 'Crear rol'],
            '/api/createPermission' => ['Administracion', 'Roles', 'Crear permiso'],
            '/api/updateRole/{id}' => ['Administracion', 'Roles', 'Actualizar rol'],
            '/api/disableRole/{id}' => ['Administracion', 'Roles', 'Inhabilitar rol'],
            '/api/restoreRole/{id}' => ['Administracion', 'Roles', 'Habilitar rol'],
            '/api/relacionarUsuarioPermiso' => ['Administracion', 'Roles', 'Asignar permiso a usuario'],
            '/api/getClients' => ['Administracion', 'Clientes', 'Listar clientes'],
            '/api/getClientsByIds/{id}' => ['Administracion', 'Clientes', 'Listar clientes por ids'],
            '/api/getUsersByClient/{id}' => ['Administracion', 'Clientes', 'Listar usuarios por cliente'],
            '/api/createClient' => ['Administracion', 'Clientes', 'Crear cliente'],
            '/api/syncClients' => ['Administracion', 'Clientes', 'Sincronizar clientes'],
            '/api/setStatusClient/{id}' => ['Administracion', 'Clientes', 'Cambiar estado de cliente'],
            '/api/updateClient/{id}' => ['Administracion', 'Clientes', 'Actualizar cliente'],
            '/api/metrics/dashboard' => ['Administracion', 'Metricas', 'Consultar metricas'],
            '/api/metrics/monthly' => ['Administracion', 'Monthly Metrics', 'Consultar metricas mensuales'],
            '/api/metrics/module-access' => ['Administracion', 'Metricas', 'Registrar acceso a modulo'],
            '/api/saveSurvey' => ['Encuesta', 'Encuesta', 'Guardar encuesta'],
            '/api/listCharges' => ['Encuesta', 'Encuesta', 'Listar cargos'],
            '/api/listClients' => ['Encuesta', 'Encuesta', 'Listar clientes'],
            '/api/getUsersBySurveyClient/{id}' => ['Encuesta', 'Encuesta', 'Listar usuarios por cliente'],
            '/api/updateSurveyClient/{id}' => ['Encuesta', 'Encuesta', 'Actualizar cliente encuesta'],
            '/api/getInformationUser/{id}' => ['Encuesta', 'Encuesta', 'Consultar usuario'],
            '/api/guardarMeta' => ['Tablero Sae', 'Metas', 'Guardar meta'],
            '/api/listarMetas' => ['Tablero Sae', 'Metas', 'Listar metas'],
            '/api/guardarObjetivos' => ['Tablero Sae', 'Metas', 'Guardar objetivo'],
            '/api/listarObjetivos' => ['Tablero Sae', 'Metas', 'Listar objetivos'],
            '/api/actualizarObjetivos' => ['Tablero Sae', 'Metas', 'Actualizar objetivo'],
            '/api/guardarTablero' => ['Tablero Sae', 'Metas', 'Guardar tablero'],
            '/api/guardarCalidad' => ['Tablero Sae', 'Cumplimiento Mensual', 'Guardar calidad'],
            '/api/listarCalidades' => ['Tablero Sae', 'Cumplimiento Mensual', 'Listar calidades'],
            '/api/verificarCalidad' => ['Tablero Sae', 'Cumplimiento Mensual', 'Verificar calidad'],
            '/api/guardarAccidente' => ['Tablero Sae', 'Cumplimiento Mensual', 'Guardar accidente'],
            '/api/guardarArchivo' => ['Tablero Sae', 'Cumplimiento Mensual', 'Guardar archivo'],
            '/api/listarArchivos' => ['Tablero Sae', 'Cumplimiento Mensual', 'Listar archivos'],
            '/api/descargar-pdf' => ['Tablero Sae', 'Cumplimiento Mensual', 'Descargar PDF'],
            '/api/deleteFile' => ['Tablero Sae', 'Cumplimiento Mensual', 'Eliminar archivo'],
            '/api/metaUnidadesExists' => ['Tablero Sae', 'Unidades programadas', 'Verificar unidades programadas'],
            '/api/createMetaUnidades' => ['Tablero Sae', 'Unidades programadas', 'Crear unidades programadas'],
            '/api/createMetaUnidadesMasivo' => ['Tablero Sae', 'Unidades programadas', 'Crear unidades masivo'],
            '/api/replaceMetaUnidadesMasivo' => ['Tablero Sae', 'Unidades programadas', 'Reemplazar unidades masivo'],
            '/api/updateMetaUnidades' => ['Tablero Sae', 'Unidades programadas', 'Actualizar unidades programadas'],
            '/api/getListUnidadesMeta' => ['Tablero Sae', 'Unidades programadas', 'Listar unidades programadas'],
            '/api/getMetaUnidades/{id}' => ['Tablero Sae', 'Unidades programadas', 'Consultar unidades programadas'],
            '/api/getAreas/{id}' => ['Tablero Sae', 'Unidades programadas', 'Listar areas'],
            '/api/getDailyUnitsOfDay/{id}/{id}' => ['Tablero Sae', 'Cumplimiento Diarios', 'Consultar unidades del dia'],
            '/api/createUnidadesDiarias' => ['Tablero Sae', 'Cumplimiento Diarios', 'Crear unidades diarias'],
            '/api/createUnidadesDiariasMasivo' => ['Tablero Sae', 'Cumplimiento Diarios', 'Crear unidades diarias masivo'],
            '/api/updateUnidadesDiarias' => ['Tablero Sae', 'Cumplimiento Diarios', 'Actualizar unidades diarias'],
            '/api/getListUnidadesDiarias/{id}' => ['Tablero Sae', 'Cumplimiento Diarios', 'Listar unidades diarias'],
            '/api/getUnidadesDiariaID/{id}' => ['Tablero Sae', 'Cumplimiento Diarios', 'Consultar unidad diaria'],
        ];

        [$module, $submodule, $action] = $routes[$normalizedRoute] ?? ['Sin clasificar', 'Sin clasificar', 'Consultar'];

        return compact('module', 'submodule', 'action');
    }

    private function metricUser(Request $request)
    {
        return $request->user() ?? Auth::guard('sanctum')->user();
    }

    private function isReportableActivity(string $action): bool
    {
        return ! Str::startsWith($action, ['Listar', 'Consultar', 'Ver ', 'Verificar']);
    }

    private function isCriticalAction(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->is('api/verificarTokenLogin');
    }
}
