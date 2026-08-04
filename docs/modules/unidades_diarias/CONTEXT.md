# Contexto - Unidades diarias

## Objetivo

Administrar la programación mensual y diaria de unidades por cliente y área. El módulo define una meta mensual de unidades, permite distribuirla en fechas del mismo mes, conserva versiones de las metas actualizadas y expone la programación diaria a integraciones externas como IMEC+/Groot.

## Alcance

- Crear una meta mensual de unidades para un cliente y área.
- Crear en una transacción una meta mensual junto con varias unidades diarias.
- Reemplazar mediante carga masiva una meta existente y su distribución diaria.
- Verificar si existen unidades programadas para un cliente y mes.
- Listar metas mensuales del cliente y presentar el nombre del área suministrado por el consumidor.
- Consultar y versionar una meta mensual.
- Crear una unidad diaria asociada a la versión activa más reciente de la meta.
- Listar unidades diarias del contexto mensual, cliente y área de una meta.
- Consultar y actualizar una unidad diaria.
- Consultar áreas de un cliente mediante el servicio IMEC+ desde un endpoint backend.
- Exponer a integraciones autorizadas las unidades de una fecha, separadas por área y acompañadas de totales.
- El módulo administra programación; no registra producción ejecutada ni calcula cumplimiento real.

## Usuarios

- Usuarios de planeación que administran metas mensuales y su distribución diaria.
- Usuarios del frontend con los permisos funcionales de unidades mensuales y diarias definidos en el menú del módulo.
- Sistemas externos que consumen `getDailyUnitsOfDay` mediante el encabezado configurado en `TYPE_KEY_CLIENTS` y el valor configurado en `API_KEY_CLIENTS`.
- Excepto por la validación manual del token en `getDailyUnitsOfDay`, las rutas backend relacionadas no tienen middleware de autenticación o autorización declarado en `routes/api.php`.

## Flujo funcional

### Creación mensual individual

1. `POST /api/createMetaUnidades` recibe valor, fecha, cliente externo, área y usuario.
2. Resuelve `cliente_endpoint_id` a `clientes.id`.
3. Busca una meta activa para la combinación de cliente, año/mes y área.
4. Si existe responde HTTP `409`; si no existe, crea `meta_unidades` con `actualizaciones = 1`.

### Creación mensual masiva

1. `POST /api/createMetaUnidadesMasivo` recibe los datos mensuales y un arreglo no vacío de unidades diarias.
2. Verifica que todas las fechas diarias pertenezcan al mismo año y mes de `fecha_meta`.
3. Rechaza una meta activa existente para el mismo cliente, mes y área.
4. Dentro de una transacción crea la meta y todos los registros de `unidades_diarias` relacionados.

### Reemplazo masivo

1. `POST /api/replaceMetaUnidadesMasivo` recibe el identificador de la meta existente, datos mensuales y nueva distribución diaria.
2. Comprueba cliente, área, mes y pertenencia de todas las fechas al periodo.
3. Dentro de una transacción elimina lógicamente las unidades anteriores y la meta anterior.
4. Crea una nueva meta con `actualizaciones + 1` y crea sus nuevas unidades diarias.

### Actualización versionada de meta

1. `PUT /api/updateMetaUnidades` recibe nuevo valor, usuario, meta y motivo.
2. Crea una nueva fila de `meta_unidades`, conservando fecha, cliente y área, e incrementa `actualizaciones`.
3. Guarda el motivo en la versión anterior, cambia su `activo` a `n` y la elimina lógicamente.
4. Reasigna a la nueva versión las unidades diarias que apuntaban a la anterior.

### Creación y mantenimiento diario

1. `POST /api/createUnidadesDiarias` recibe valor, fecha, cliente, área y usuario.
2. Busca metas activas del mismo cliente, mes y área.
3. Rechaza la operación si no existe meta o si ya existe una unidad activa para esa fecha dentro de las metas encontradas.
4. Elige la meta con mayor número de `actualizaciones` y crea la unidad diaria.
5. `GET /api/getListUnidadesDiarias/{meta_unidades_id}` lista las unidades activas del mismo cliente, área y mes que la meta consultada.
6. `GET /api/getUnidadesDiariaID/{unidades_diaria_id}` devuelve fecha y valor.
7. `POST /api/updateUnidadesDiarias` reemplaza valor y usuario en la misma fila.

### Integración diaria externa

1. `GET /api/getDailyUnitsOfDay/{date}/{client_id}` compara el encabezado configurado con la clave de aplicación.
2. Une metas, unidades diarias, clientes y áreas para la fecha y el identificador externo del cliente.
3. Excluye metas, unidades y clientes eliminados lógicamente.
4. Devuelve área, valor diario, valor de meta y totales calculados mediante funciones de ventana.

## Tablas y campos clave

La conexión local configurada en `.env` usa PostgreSQL con `clients, surveys` como `search_path`. La estructura se verificó mediante consultas de solo lectura.

### `clients.meta_unidades`

- `meta_unidades_id`: `bigint`, clave primaria.
- `valor`: `integer`, obligatorio; total mensual programado.
- `fecha_meta`: `date`, obligatoria; periodo mensual.
- `actualizaciones`: `smallint`, anulable; número de versión.
- `clientes_id`: `bigint`, obligatorio y clave foránea hacia `clients.clientes.id`.
- `usuario`: `varchar`, obligatorio; usuario atribuido a la operación.
- `activo`: `char(1)`, obligatorio, valor predeterminado `s`.
- `area_id_groot`: `bigint`, anulable; referencia lógica al área externa.
- `motivo_actualizacion`: `varchar`, anulable; motivo almacenado en una versión sustituida.
- `deleted_at`: eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

### `clients.unidades_diarias`

- `unidades_diarias_id`: `bigint`, clave primaria.
- `valor`: `integer`, obligatorio; unidades programadas para la fecha.
- `fecha_programacion`: `date`, obligatoria.
- `actualizaciones`: `smallint`, anulable; existe en la estructura, pero el controlador actual no lo incrementa.
- `meta_unidades_id`: `bigint`, obligatorio y clave foránea hacia `clients.meta_unidades.meta_unidades_id`.
- `usuario`: `varchar`, obligatorio.
- `activo`: `char(1)`, obligatorio, valor predeterminado `s`.
- `deleted_at`: eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

### `clients.clientes`

- `id`: clave primaria almacenada en `meta_unidades.clientes_id`.
- `cliente_endpoint_id`: identificador externo recibido por las APIs.
- `nombre`, `activo`: información y estado del cliente.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

### `public.area`

- `area_id`: `integer`, clave primaria comparada con `meta_unidades.area_id_groot`.
- `cliente_id`: identificador del cliente en el dominio de áreas.
- `nombre_area`: nombre presentado por la integración.
- `usuario`, `activo`, `deleted_at`, `created_at`, `updated_at`: estado y auditoría.

La base no declara una clave foránea entre `clients.meta_unidades.area_id_groot` y `public.area.area_id`.

## Archivos relevantes

### Backend principal

- `routes/api.php`: registra las rutas mensuales, diarias, de áreas y de integración.
- `app/Http/Controllers/MetaUnidadesController.php`: creación individual y masiva, reemplazo, existencia, listado, consulta, versionado y consulta de áreas.
- `app/Models/MetaUnidades.php`: configura la meta mensual y sus relaciones con cliente y unidades diarias.
- `app/Http/Controllers/UnidadesDiariasController.php`: creación, listado, consulta, actualización e integración diaria.
- `app/Models/UnidadesDiarias.php`: configura la programación diaria y su relación con la meta.
- `app/Models/Cliente.php`: resuelve el cliente externo y relaciona sus metas.

### Migraciones

- `database/migrations/2025_03_10_163047_create_meta_unidades_table.php`.
- `database/migrations/2025_03_10_171901_create_unidades_diarias_table.php`.
- `database/migrations/2025_04_11_185630_add_area_to_meta_unidades_table.php`.
- `database/migrations/2025_05_13_212616_add_motivo_actualizacion_to_meta_unidades_table.php`.
- `database/migrations/2024_06_19_160520_create_clientes_table.php`.

### Configuración e integración

- `config/app.php`: obtiene `TYPE_KEY_CLIENTS` y `API_KEY_CLIENTS` del entorno.
- Servicio de áreas IMEC+: `https://imecplusdev.ienm.com.co:8443/api/area/listarCliente/{clienteID}` en `getAreasImec`.
- Esquema `public.area`: fuente local usada por `getDailyUnitsOfDay` para nombre e identificador de área.

### Frontend consumidor

- `Modulo-clientes-Frontend/pages/objetivos/unidades.vue` y `unidadesTable.vue`.
- `Modulo-clientes-Frontend/components/objetivos/FormUnitsMonthly.vue` y `FormUnitsDaily.vue`.
- `Modulo-clientes-Frontend/components/objetivos/ModalUnitsDaily.vue`.
- `Modulo-clientes-Frontend/components/objetivos/ModalUpdateUnits.vue`.
- `Modulo-clientes-Frontend/components/objetivos/ModalUpdateUnitsDaily.vue`.
- `Modulo-clientes-Frontend/components/objetivos/ModalReasonUpdateGoal.vue`.
- `Modulo-clientes-Frontend/composables/objetivos/UnitsApi.ts`.
- `Modulo-clientes-Frontend/composables/objetivos/UnitsDailyApi.ts`.
- `Modulo-clientes-Frontend/interfaces/objetives.ts`.

El frontend actual consulta áreas directamente mediante su `apiGroot`; no consume `MetaUnidadesController::getAreasImec` en el composable revisado.

## Rutas y validaciones actuales

### Metas mensuales

- `POST /api/metaUnidadesExists`: `fecha_meta` date y `cliente_endpoint_id` integer, ambos obligatorios.
- `POST /api/createMetaUnidades`: `valor` integer, `fecha_meta` date, cliente integer, área integer y usuario string, todos obligatorios.
- `POST /api/createMetaUnidadesMasivo`: mismos campos y `unidades_diarias` como arreglo no vacío; cada valor diario es integer mínimo `0` y cada fecha es date.
- `POST /api/replaceMetaUnidadesMasivo`: agrega `meta_unidades_id` integer obligatorio y aplica las mismas reglas masivas.
- `POST /api/getListUnidadesMeta`: exige `arraysAreas` array y `cliente_endpoint_id` integer.
- `GET /api/getMetaUnidades/{meta_unidades_id}`: recibe el identificador por ruta.
- `PUT /api/updateMetaUnidades`: exige valor integer, usuario string, `meta_unidades_id` string y motivo string.
- `GET /api/getAreas/{clienteID}`: recibe el cliente por ruta.

### Unidades diarias

- `POST /api/createUnidadesDiarias`: exige valor integer, fecha date, cliente integer, área integer y usuario string.
- `POST /api/createUnidadesDiariasMasivo`: está registrada en rutas hacia `UnidadesDiariasController::createBulk`.
- `GET /api/getListUnidadesDiarias/{meta_unidades_id}`.
- `GET /api/getUnidadesDiariaID/{unidades_diaria_id}`.
- `POST /api/updateUnidadesDiarias`: exige valor integer, usuario string e identificador string.
- `GET /api/getDailyUnitsOfDay/{date}/{client_id}`: exige además el encabezado de aplicación configurado.

## Reglas de negocio

- La meta mensual se identifica funcionalmente por cliente, año/mes y área.
- La creación individual y masiva rechaza otra meta activa para esa combinación.
- Cada unidad diaria debe pertenecer al mismo mes de su meta.
- Solo se admite una unidad diaria activa por fecha dentro del contexto de cliente, mes y área.
- La creación diaria usa la versión activa con mayor valor de `actualizaciones`.
- La actualización mensual genera una nueva versión y elimina lógicamente la anterior.
- El reemplazo masivo sustituye tanto la meta como toda su distribución diaria en una transacción.
- El listado mensual recibe desde el consumidor el catálogo de áreas y reemplaza identificadores conocidos por nombres.
- Los modelos `MetaUnidades` y `UnidadesDiarias` aplican `SoftDeletes`.
- La integración externa requiere coincidencia exacta del token configurado.

## Dependencias

- Laravel: validación, Eloquent, Query Builder, transacciones, cliente HTTP y respuestas JSON.
- PHP `DateTime`: normalización y comparación de periodos.
- PostgreSQL: esquemas `clients` y `public`, claves foráneas y funciones de ventana.
- Módulo de clientes: traducción de `cliente_endpoint_id` a `clientes.id`.
- Servicio IMEC+/Groot y tabla `public.area`: catálogo de áreas.
- Variables `TYPE_KEY_CLIENTS` y `API_KEY_CLIENTS` de `.env` para la integración.
- Frontend de unidades para captura, carga masiva, consulta y actualización.

## Riesgos

- No hay restricciones únicas de base para cliente/mes/área ni cliente/área/fecha; solicitudes concurrentes pueden crear duplicados.
- El versionado individual de una meta no usa transacción y puede dejar versiones o relaciones en estado parcial.
- Si una meta no tiene unidades relacionadas, el versionado crea y elimina versiones antes de responder conflicto porque la actualización masiva de claves afecta cero filas.
- `area_id_groot` es nullable y no tiene clave foránea; pueden almacenarse áreas inexistentes o pertenecientes a otro cliente.
- La mayoría de endpoints no valida propiedad del recurso respecto del cliente autenticado y no tiene protección backend.
- Los campos `activo` existen, pero las consultas se basan principalmente en `deleted_at` y no aplican uniformemente el estado.
- El endpoint externo calcula `SUM(mu.valor) OVER ()` sobre filas diarias, por lo que una misma meta mensual puede repetirse y aumentar artificialmente el total.
- `getDailyUnitsOfDay` no filtra el estado o eliminación lógica de `public.area`.
- Las consultas por mes usan `LIKE` sobre columnas `date`; las tablas `clients` revisadas solo tienen índices de clave primaria.
- Las respuestas de error exponen mensajes internos de excepciones; el endpoint externo no usa códigos HTTP diferenciados en todos los errores.

## Consideraciones

- Distinguir `meta_unidades` de la tabla `meta` del Tablero SAE: representan dominios y métricas diferentes.
- Mantener la distribución diaria vinculada a la versión activa al actualizar o reemplazar una meta.
- Verificar siempre cliente, área y periodo conjuntamente antes de consultar o mutar datos.
- Coordinar nombres e identificadores de área entre IMEC+/Groot, el frontend y `public.area`.
- Tratar `usuario` como dato proporcionado por el consumidor mientras no se derive de una identidad backend autenticada.
