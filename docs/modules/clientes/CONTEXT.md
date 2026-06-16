# Contexto - Clientes

## Objetivo

Documentar la API backend que soporta el submodulo administrativo `Clientes`.

## Modelos Y Tablas

1. `App\Models\Cliente`
   - Tabla: `clientes`.
   - Campos relevantes: `id`, `nombre`, `cliente_endpoint_id`, `activo`, `deleted_at`.
   - Relacion con usuarios: `belongsToMany(User::class)` por `cliente_user`.

2. `App\Models\survey\Clients`
   - Tabla: `clients` dentro del esquema `surveys` (`surveys.clients` en notacion calificada de PostgreSQL).
   - Campos relevantes: `clients_id`, `name`, `feed_value`, `cost_center`, `overtime`, `city_id`, `active`, `deleted_at`.

3. `App\Models\ClienteUser`
   - Tabla: `cliente_user`.
   - Campos relevantes: `id`, `user_id`, `cliente_id`.
   - `cliente_id` apunta a `clientes.id`.

## Permisos

- El permiso de visibilidad del submodulo es `view_clients`.
- `PermissionController@getListPermissions` asegura que `view_clients` exista para poder asignarlo desde la vista de Usuarios.
- El login debe devolver permisos de rol y permisos directos de `user_permission`, sin duplicados.
- Para ver el submodulo en frontend, el usuario tambien necesita `view_administration`, porque `Clientes` cuelga del grupo `Administracion`.
- Queda pendiente definir permisos mas finos para acciones como crear, editar y sincronizar clientes.

## Endpoints

- `GET api/getClients`
  - Controlador: `ClienteController@getClients`.
  - Lista clientes de la tabla `clientes`.

- `GET api/getClientsByIds/{arrayIds}`
  - Controlador: `ClienteController@getClientsByIds`.
  - Lista clientes por `cliente_endpoint_id`.

- `GET api/getUsersByClient/{id}`
  - Controlador: `ClienteController@getUsersByClientId`.
  - Recibe el `id` interno de `clientes`.
  - Consulta `cliente_user` para listar usuarios asociados al cliente.
  - Devuelve `id`, `username`, `fullname`, `email`, `cellphone`, estado y datos de eliminacion.

- `GET api/getUsersBySurveyClient/{id}`
  - Controlador: `SurveyController@getUsersBySurveyClientId`.
  - Recibe `surveys.clients.clients_id`.
  - Busca el cliente equivalente en `clientes` por `clientes.cliente_endpoint_id`.
  - Consulta `cliente_user` usando el `clientes.id` encontrado para listar usuarios asociados.
  - Devuelve `id`, `username`, `fullname`, `email`, `cellphone`, estado y datos de eliminacion.

- `PUT api/updateClient/{id}`
  - Controlador: `ClienteController@updateClient`.
  - Actualiza `nombre` y `cliente_endpoint_id` de `clientes`.
  - Requiere token de backend en header configurado.

- `PUT api/setStatusClient/{id}`
  - Controlador: `ClienteController@setStatusClient`.
  - Alterna el estado del cliente en `clientes` usando soft delete.
  - Si el cliente esta activo, lo deshabilita con `activo = n` y `deleted_at` lleno.
  - Si el cliente esta deshabilitado, lo restaura con `activo = s` y `deleted_at = NULL`.
  - Si existe el cliente equivalente en `surveys.clients`, sincroniza `deleted_at` y `active`.
  - Requiere token de backend en header configurado.

- `POST api/createClient`
  - Controlador: `ClienteController@createClient`.
  - Crea clientes en `clientes`.
  - Valida `nombre` y `cliente_endpoint_id` unico.
  - Requiere token de backend en header configurado.

- `POST api/syncClients`
  - Controlador: `ClienteController@syncClients`.
  - Lee desde `public.cliente`.
  - Sincroniza hacia `clients.clientes` todos los registros de la fuente publica.
  - Si `public.cliente.deleted_at` viene lleno o `public.cliente.activo` es diferente de `s`, marca el cliente como deshabilitado en `clientes` con `deleted_at` lleno y `activo = n`.
  - Si un cliente vuelve activo en `public.cliente`, restaura el soft delete en `clientes` dejando `deleted_at = NULL`.
  - Para Surveys usa `surveys.clients`.
  - Primero busca un registro existente por `clients_id`, `cliente_id` o `id`, y luego por nombre normalizado (`name` o `nombre`).
  - Si ya existe en Surveys, lo actualiza incluyendo `deleted_at` y `active`.
  - Si no existe, lo crea cuando el `cliente_id` exista en `public.clientes_activos` o cuando venga deshabilitado desde `public.cliente`.
  - Si un cliente de Surveys viene deshabilitado desde `public.cliente`, queda con `deleted_at` lleno y `active = false`; si vuelve activo, queda con `deleted_at = NULL` y `active = true`.
  - Si no existe `public.clientes_activos`, omite la sincronizacion completa de Surveys.
  - En Surveys mantiene `surveys.clients.clients_id = public.cliente.cliente_id` para conservar el cruce con `clients.clientes.cliente_endpoint_id`.
  - Requiere token de backend en header configurado.

- `GET api/listClients`
  - Controlador: `SurveyController@getListClients`.
  - Lista clientes de `surveys.clients`.

- `PUT api/updateSurveyClient/{id}`
  - Controlador: `SurveyController@updateSurveyClient`.
  - Actualiza `name`, `feed_value`, `cost_center`, `overtime` y `city_id`.
  - Requiere token de backend en header configurado.

- `GET api/getClientsByUserId`
  - Controlador: `ClienteUserController@getClientsByUserId`.
  - Usa `Auth::user()` para consultar el usuario autenticado.
  - Devuelve clientes asociados desde `cliente_user`, omitiendo relaciones o clientes con soft delete.
  - Devuelve 404 si no hay usuario autenticado o si el usuario no tiene clientes asociados.

## Consideraciones

- La tabla fuente del esquema `public` es `public.cliente`.
- La sincronizacion usa `public.cliente.cliente_id` como `clients.clientes.cliente_endpoint_id`.
- La deshabilitacion se toma de `public.cliente.deleted_at` lleno o de `public.cliente.activo` diferente de `s`.
- La deshabilitacion se propaga con soft delete hacia `clientes.deleted_at` y `surveys.clients.deleted_at`.
- `surveys.clients.clients_id` debe corresponder a `public.cliente.cliente_id`; para Surveys se actualiza por coincidencia de `clients_id` o nombre, y si encuentra una coincidencia por nombre con id desalineado intenta corregir el `clients_id`.
- Cambiar `cliente_endpoint_id` puede afectar login, seleccion de cliente, objetivos y relacionamientos existentes.
- La API crea o actualiza clientes en `surveys.clients` durante la sincronizacion si pertenecen a `public.clientes_activos`; desde la pantalla solo se editan registros existentes.
- Si no existe la tabla `clients` dentro del esquema `surveys`, `POST api/syncClients` omite la parte de Surveys y sincroniza solo `clients.clientes` desde la fuente publica.
- Si `public.clientes_activos` no existe, no se crean ni se actualizan registros en Surveys durante `POST api/syncClients`.
- La relacion usuario-cliente se lee desde `cliente_user.cliente_id`, que apunta a `clientes.id`.
- Para clientes de encuestas, la relacion con usuarios no se consulta con `surveys.clients.clients_id` directo contra `cliente_user`; primero se traduce por `clientes.cliente_endpoint_id`.
- Los endpoints de Surveys (`listClients`, `updateSurveyClient`, `getUsersBySurveyClient`) devuelven un mensaje controlado cuando no existe la tabla `clients` dentro del esquema `surveys`, en lugar de exponer el error SQL.
- La suite de pruebas del modulo `Clientes` usa SQLite en memoria y simula los esquemas `public` y `surveys` para validar la sincronizacion sin depender de la base real.
- Para ejecutar las pruebas del modulo, usar PHP 8.3 de Laragon:
  `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan test tests\Feature\ClienteApiTest.php tests\Unit\ClienteModelTest.php`.
