# Bitacora - Clientes

Registro de cambios backend del modulo `Clientes`.

## 2026-05-22

### Documentacion inicial

- Se documento el modulo `clientes` para backend.
- Se describieron modelos, tablas y endpoints relacionados.

### Endpoint de actualizacion

- Se agrego `PUT api/updateClient/{id}`.
- Se implemento `ClienteController@updateClient`.
- Se valida `nombre` obligatorio.
- Se valida `cliente_endpoint_id` obligatorio, entero y unico en `clientes`, ignorando el cliente actual.
- Se protege la peticion con el token configurado del backend.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `routes/api.php`

### Creacion y edicion en surveys

- Se agrego `POST api/createClient`.
- Se implemento `ClienteController@createClient`.
- Se agrego `PUT api/updateSurveyClient/{id}`.
- Se implemento `SurveyController@updateSurveyClient`.
- Se validan campos principales antes de guardar.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/SurveyController.php`
- `routes/api.php`

### Sincronizacion desde public

- Se agrego `POST api/syncClients`.
- Se implemento `ClienteController@syncClients`.
- La fuente es `public.cliente`.
- Se sincroniza hacia `clientes` desde `public.cliente`.
- En `surveys.clients`, se actualizan clientes existentes por coincidencia de `clients_id` o nombre normalizado.
- En `surveys.clients`, solo se crean nuevos clientes si existen en `public.clientes_activos`.
- Se fuerza `surveys.clients.clients_id = public.cliente.cliente_id` para mantener alineada la relacion con `clients.clientes.cliente_endpoint_id`.
- Se devuelve resumen de creados y actualizados por destino.
- Se corrigen las secuencias de `clientes.id` y `surveys.clients.clients_id` durante la sincronizacion para evitar errores de llave primaria duplicada cuando el autoincremental queda atrasado.
- Se agrega conteo de clientes omitidos en Surveys para no crear en `surveys.clients` clientes que solo pertenecen a `clientes`.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `routes/api.php`

### Correccion de secuencia al crear clientes

- `POST api/createClient` sincroniza la secuencia de `clientes.id` antes de insertar.
- Esto evita errores como `llave duplicada viola restriccion de unicidad clientes_pkey` cuando PostgreSQL intenta reutilizar un `id` existente.

Archivos:
- `app/Http/Controllers/ClienteController.php`

### Usuarios asociados por cliente

- Se agrego `GET api/getUsersByClient/{id}`.
- El endpoint consulta `cliente_user` usando el `id` interno de `clientes`.
- Devuelve usuarios asociados con usuario, nombre, correo, celular y estado.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `routes/api.php`

## 2026-05-25

### Usuarios asociados por cliente de encuestas

- Se agrego `GET api/getUsersBySurveyClient/{id}`.
- Se implemento `SurveyController@getUsersBySurveyClientId`.
- El endpoint recibe `surveys.clients.clients_id`.
- Para ubicar usuarios asociados, mapea `surveys.clients.clients_id` contra `clientes.cliente_endpoint_id` y luego consulta `cliente_user.cliente_id`.
- Devuelve el mismo formato usado por `GET api/getUsersByClient/{id}`: usuario, nombre, correo, celular y estado.
- Esto permite ver desde la vista `Surveys` que usuarios estan asociados al cliente de encuestas.

Archivos:
- `app/Http/Controllers/SurveyController.php`
- `routes/api.php`

## 2026-05-26

### Permiso de visibilidad del modulo Clientes

- Se definio `view_clients` como permiso especifico para mostrar el submodulo `Clientes` dentro de `Administracion`.
- Se evita usar migracion para crear el permiso, por precaucion operativa sobre la base de datos.
- `PermissionController@getListPermissions` asegura la existencia de `view_clients` cuando la vista de usuarios carga la lista de permisos.
- Se confirmo en base de datos que `view_clients` existe con `guard_name = sanctum`.
- Se asigno `view_clients` al rol `Dev` y al usuario `DEVUSER`, para que tenga el mismo comportamiento esperado que `view_users` dentro de `Administracion`.

Archivos:
- `app/Http/Controllers/PermissionController.php`

## 2026-05-28

### Pruebas unitarias y feature del modulo Clientes

- Se agrego suite de pruebas para el modulo backend `Clientes`.
- Se cubrieron endpoints principales:
  - `GET api/getClients`.
  - `GET api/getClientsByIds/{arrayIds}`.
  - `GET api/getUsersByClient/{id}`.
  - `POST api/createClient`.
  - `PUT api/updateClient/{id}`.
  - `POST api/syncClients`.
  - `GET api/getClientsByUserId`.
- Se cubrieron validaciones de token invalido, payload requerido, `cliente_endpoint_id` duplicado, clientes sin usuarios y cliente inexistente.
- Se cubrio la sincronizacion desde `public.cliente` hacia `clientes` y `surveys.clients`, incluyendo:
  - clientes creados en `clientes`;
  - clientes actualizados en `clientes`;
  - restauracion de cliente con soft delete;
  - clientes creados y actualizados en `surveys.clients`;
  - clientes omitidos en Surveys cuando no aparecen en `public.clientes_activos`.
- Se agregaron pruebas unitarias del modelo `Cliente` para `fillable` y relaciones con `users`, `tablero_sae` y `meta_unidades`.
- Se configuraron las pruebas con SQLite en memoria y esquemas simulados `public` y `surveys`, para no depender de la base real.

Archivos:
- `tests/Feature/ClienteApiTest.php`
- `tests/Unit/ClienteModelTest.php`

### Ajustes para hacer testeable el modulo Clientes

- `ClienteController` evita ejecutar sincronizacion de secuencias PostgreSQL cuando el driver no es `pgsql`.
- `ClienteController@getUsersByClientId` usa una expresion de nombre completo compatible con SQLite en pruebas y conserva `CONCAT` para PostgreSQL.
- `routes/console.php` protege funciones globales con `function_exists` para evitar redeclaraciones durante el bootstrap de tests.
- `ClienteUserController@getClientsByUserId` fue corregido para usar `Auth::user()` y eliminar credenciales de prueba quemadas.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/ClienteUserController.php`
- `routes/console.php`

### Ejecucion de pruebas

Comando usado:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan test tests\Feature\ClienteApiTest.php tests\Unit\ClienteModelTest.php
```

Resultado:
- 19 pruebas exitosas.
- 60 assertions.

Nota:
- La suite completa del proyecto sigue fallando por migraciones existentes fuera del modulo `Clientes`: la migracion de `accidentes` intenta crear una llave foranea contra `tablero_sae` antes de que exista la relacion esperada en la base de testing.

## 2026-05-29

### Permiso aplicado en base local para DEVUSER

- Se reviso la base local y se encontro que el permiso `view_clients` no existia todavia.
- Se creo `view_clients` con `guard_name = sanctum`.
- Se asigno `view_clients` al rol `Dev`.
- Se asigno `view_clients` directamente al usuario `DEVUSER`.
- Se limpio la cache de permisos de Spatie.
- Se verifico que `DEVUSER` tenga `view_administration` y `view_clients`, necesarios para mostrar `Administracion > Clientes` en el menu del frontend.

### Sincronizacion tolerante sin esquema Surveys

- Se reviso el flujo completo del modulo `Clientes` y la documentacion asociada.
- Se corrigio `POST api/syncClients` para que no falle cuando no existe la tabla `clients` dentro del esquema `surveys`.
- Si falta la tabla `surveys.clients`, la sincronizacion actualiza solo `clientes` desde `public.cliente` y devuelve un mensaje indicando que Surveys fue omitido.
- Si falta `public.clientes_activos`, se omite la sincronizacion de `surveys.clients`.
- `GET api/listClients`, `PUT api/updateSurveyClient/{id}` y `GET api/getUsersBySurveyClient/{id}` ahora devuelven mensajes controlados si no existe la tabla `clients` dentro del esquema `surveys`.
- Los listados de usuarios por cliente toleran la ausencia de `surveys.customer_contact` y devuelven datos de contacto como `null`.
- El frontend muestra el mensaje del backend cuando la pestaña Surveys no esta disponible.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/SurveyController.php`
- `docs/modules/clientes/CONTEXT.md`
- `docs/modules/clientes/PENDING.md`
- `composables/administration/clientsApi.ts`
- `pages/administration/clients.vue`

### Deteccion de tablas por schema para sincronizacion

- Se aclaro que `surveys.clients` significa tabla `clients` dentro del schema `surveys`.
- `POST api/syncClients` usa `public.cliente` como fuente publica.
- La sincronizacion hacia el schema `clients` sigue usando `clients.clientes` mediante el modelo `Cliente`.
- La sincronizacion hacia Surveys usa `surveys.clients`.
- Para Surveys se soportan columnas de id `clients_id`, `cliente_id` o `id`, y columnas de nombre `name` o `nombre`.
- `GET api/listClients`, `PUT api/updateSurveyClient/{id}` y `GET api/getUsersBySurveyClient/{id}` usan `surveys.clients`.
- El frontend acepta `name` o `nombre` al renderizar la vista de Surveys.
- Se agrego una prueba feature para validar que Surveys se omite si no existe `surveys.clients`.
- Se corrigio la sincronizacion para que los clientes creados o encontrados por nombre en `surveys.clients` queden con `clients_id` igual a `public.cliente.cliente_id`.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/SurveyController.php`
- `tests/Feature/ClienteApiTest.php`
- `docs/modules/clientes/CONTEXT.md`
- `docs/modules/clientes/PENDING.md`
- `composables/administration/dataClients.ts`

### Validacion de sincronizacion contra base local

Motivo:
- Confirmar las tablas reales de clientes en los schemas locales y dejar la sincronizacion alineada con esa estructura.

Hallazgos:
- `public.cliente` es la fuente publica de clientes.
- `clients.clientes` es la tabla del modulo Clients.
- `surveys.clients` es la tabla del schema Surveys.
- No se encontraron duplicados activos por ID ni por nombre en `public.cliente`, `clients.clientes`, `surveys.clients` ni `public.clientes_activos`.
- Se observaron algunos registros activos en `surveys.clients` sin pareja activa en `public.cliente` o `clients.clientes`, asociados a historicos o soft deletes.

Cambios realizados:
- `POST api/syncClients` quedo usando `public.cliente` como unica fuente publica.
- La sincronizacion hacia `surveys.clients` conserva `clients_id = public.cliente.cliente_id`.
- Si un cliente de Surveys se encuentra por nombre con ID desalineado y no existe conflicto de llave, el sincronizador corrige el `clients_id`.

Verificacion:
- `artisan test tests\Feature\ClienteApiTest.php tests\Unit\ClienteModelTest.php`
- Resultado: 21 pruebas exitosas, 67 assertions.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `tests/Feature/ClienteApiTest.php`
- `docs/modules/clientes/CONTEXT.md`

## 2026-06-04

### Sincronizacion de clientes deshabilitados

Motivo:
- Propagar a `clientes` y `surveys.clients` los clientes deshabilitados desde la fuente `public.cliente`.

Hallazgos:
- En la base local, los clientes deshabilitados en `public.cliente` aparecen con `deleted_at` lleno y `activo = n`.
- `clientes` ya usaba soft delete para algunos clientes deshabilitados.
- `surveys.clients` no tenia registros deshabilitados aunque la tabla soporta `deleted_at` y `active`.

Cambios realizados:
- `POST api/syncClients` ahora lee todos los registros de `public.cliente`, incluyendo los que tienen `deleted_at`.
- Si `public.cliente.deleted_at` viene lleno o `activo` es diferente de `s`, se sincroniza como deshabilitado.
- En `clientes`, el cliente queda con `deleted_at` lleno y `activo = n`.
- En `surveys.clients`, el cliente queda con `deleted_at` lleno y `active = false`.
- Si el cliente vuelve activo en `public.cliente`, la sincronizacion restaura `deleted_at = NULL` y marca el estado activo.
- Los clientes deshabilitados pueden crearse en `surveys.clients` aunque no esten en `public.clientes_activos`, para que el estado deshabilitado quede visible y alineado.

Verificacion:
- `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan test tests\Feature\ClienteApiTest.php tests\Unit\ClienteModelTest.php`
- Resultado: 21 pruebas exitosas, 69 assertions.

Archivos:
- `app/Http/Controllers/ClienteController.php`
- `tests/Feature/ClienteApiTest.php`
- `docs/modules/clientes/CONTEXT.md`
