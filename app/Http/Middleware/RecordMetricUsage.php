<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        return $request->is('api/*')
            && ! $request->is('api/metrics/*')
            && $request->user() !== null;
    }

    private function record(Request $request, Response $response, float $startedAt): void
    {
        $user = $request->user();
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
            '/api/policy' => ['Politicas', 'Administracion', 'Ver politica'],
            '/api/policy/status' => ['Politicas', 'Administracion', 'Consultar estado de politica'],
            '/api/policy/accept' => ['Politicas', 'Administracion', 'Aceptar politica'],
            '/api/getUsers' => ['Usuarios', 'Administracion', 'Listar usuarios'],
            '/api/getRoles' => ['Usuarios', 'Administracion', 'Listar roles de usuario'],
            '/api/getDataUserId/{id}' => ['Usuarios', 'Administracion', 'Consultar usuario'],
            '/api/getEmployeesImec/{id}' => ['Usuarios', 'Administracion', 'Listar empleados IMEC'],
            '/api/createUser' => ['Usuarios', 'Administracion', 'Crear usuario'],
            '/api/updateUser/{id}' => ['Usuarios', 'Administracion', 'Actualizar usuario'],
            '/api/setStatusUser/{id}' => ['Usuarios', 'Administracion', 'Cambiar estado de usuario'],
            '/api/resetUser/{id}' => ['Usuarios', 'Administracion', 'Restablecer usuario'],
            '/api/getListPermissions' => ['Roles', 'Administracion', 'Listar permisos'],
            '/api/getAdminRoles' => ['Roles', 'Administracion', 'Listar roles'],
            '/api/getDisabledAdminRoles' => ['Roles', 'Administracion', 'Listar roles inhabilitados'],
            '/api/createRole' => ['Roles', 'Administracion', 'Crear rol'],
            '/api/updateRole/{id}' => ['Roles', 'Administracion', 'Actualizar rol'],
            '/api/disableRole/{id}' => ['Roles', 'Administracion', 'Inhabilitar rol'],
            '/api/restoreRole/{id}' => ['Roles', 'Administracion', 'Habilitar rol'],
            '/api/relacionarUsuarioPermiso' => ['Roles', 'Administracion', 'Asignar permiso a usuario'],
            '/api/getClients' => ['Clientes', 'Administracion', 'Listar clientes'],
            '/api/getClientsByIds/{id}' => ['Clientes', 'Administracion', 'Listar clientes por ids'],
            '/api/getUsersByClient/{id}' => ['Clientes', 'Administracion', 'Listar usuarios por cliente'],
            '/api/createClient' => ['Clientes', 'Administracion', 'Crear cliente'],
            '/api/syncClients' => ['Clientes', 'Administracion', 'Sincronizar clientes'],
            '/api/setStatusClient/{id}' => ['Clientes', 'Administracion', 'Cambiar estado de cliente'],
            '/api/updateClient/{id}' => ['Clientes', 'Administracion', 'Actualizar cliente'],
            '/api/saveSurvey' => ['Encuesta', 'Encuesta', 'Guardar encuesta'],
            '/api/listCharges' => ['Encuesta', 'Encuesta', 'Listar cargos'],
            '/api/listClients' => ['Encuesta', 'Encuesta', 'Listar clientes'],
            '/api/getUsersBySurveyClient/{id}' => ['Encuesta', 'Encuesta', 'Listar usuarios por cliente'],
            '/api/updateSurveyClient/{id}' => ['Encuesta', 'Encuesta', 'Actualizar cliente encuesta'],
            '/api/getInformationUser/{id}' => ['Encuesta', 'Encuesta', 'Consultar usuario'],
            '/api/guardarMeta' => ['Metas', 'Tablero Sae', 'Guardar meta'],
            '/api/listarMetas' => ['Metas', 'Tablero Sae', 'Listar metas'],
            '/api/guardarObjetivos' => ['Metas', 'Tablero Sae', 'Guardar objetivo'],
            '/api/listarObjetivos' => ['Metas', 'Tablero Sae', 'Listar objetivos'],
            '/api/actualizarObjetivos' => ['Metas', 'Tablero Sae', 'Actualizar objetivo'],
            '/api/guardarTablero' => ['Metas', 'Tablero Sae', 'Guardar tablero'],
            '/api/guardarCalidad' => ['Cumplimiento Mensual', 'Tablero Sae', 'Guardar calidad'],
            '/api/listarCalidades' => ['Cumplimiento Mensual', 'Tablero Sae', 'Listar calidades'],
            '/api/verificarCalidad' => ['Cumplimiento Mensual', 'Tablero Sae', 'Verificar calidad'],
            '/api/guardarAccidente' => ['Cumplimiento Mensual', 'Tablero Sae', 'Guardar accidente'],
            '/api/guardarArchivo' => ['Cumplimiento Mensual', 'Tablero Sae', 'Guardar archivo'],
            '/api/listarArchivos' => ['Cumplimiento Mensual', 'Tablero Sae', 'Listar archivos'],
            '/api/descargar-pdf' => ['Cumplimiento Mensual', 'Tablero Sae', 'Descargar PDF'],
            '/api/deleteFile' => ['Cumplimiento Mensual', 'Tablero Sae', 'Eliminar archivo'],
            '/api/metaUnidadesExists' => ['Unidades programadas', 'Tablero Sae', 'Verificar unidades programadas'],
            '/api/createMetaUnidades' => ['Unidades programadas', 'Tablero Sae', 'Crear unidades programadas'],
            '/api/createMetaUnidadesMasivo' => ['Unidades programadas', 'Tablero Sae', 'Crear unidades masivo'],
            '/api/replaceMetaUnidadesMasivo' => ['Unidades programadas', 'Tablero Sae', 'Reemplazar unidades masivo'],
            '/api/updateMetaUnidades' => ['Unidades programadas', 'Tablero Sae', 'Actualizar unidades programadas'],
            '/api/getListUnidadesMeta' => ['Unidades programadas', 'Tablero Sae', 'Listar unidades programadas'],
            '/api/getMetaUnidades/{id}' => ['Unidades programadas', 'Tablero Sae', 'Consultar unidades programadas'],
            '/api/getAreas/{id}' => ['Unidades programadas', 'Tablero Sae', 'Listar areas'],
            '/api/getDailyUnitsOfDay/{id}/{id}' => ['Cumplimiento Diarios', 'Tablero Sae', 'Consultar unidades del dia'],
            '/api/createUnidadesDiarias' => ['Cumplimiento Diarios', 'Tablero Sae', 'Crear unidades diarias'],
            '/api/createUnidadesDiariasMasivo' => ['Cumplimiento Diarios', 'Tablero Sae', 'Crear unidades diarias masivo'],
            '/api/updateUnidadesDiarias' => ['Cumplimiento Diarios', 'Tablero Sae', 'Actualizar unidades diarias'],
            '/api/getListUnidadesDiarias/{id}' => ['Cumplimiento Diarios', 'Tablero Sae', 'Listar unidades diarias'],
            '/api/getUnidadesDiariaID/{id}' => ['Cumplimiento Diarios', 'Tablero Sae', 'Consultar unidad diaria'],
        ];

        [$module, $submodule, $action] = $routes[$normalizedRoute] ?? ['Sin clasificar', 'Sin clasificar', 'Consultar'];

        return compact('module', 'submodule', 'action');
    }

    private function isCriticalAction(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->is('api/verificarTokenLogin');
    }
}
