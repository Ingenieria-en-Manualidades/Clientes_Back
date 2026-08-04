# Contexto - Metas

## Objetivo

Administrar las metas porcentuales mensuales del Tablero SAE para cada cliente. Una meta define los valores esperados de cumplimiento del plan de armado, eficiencia productiva, calidad y desperdicios; el periodo y el cliente se determinan mediante el registro relacionado de `tablero_sae`.

## Alcance

- Crear los cinco valores de una meta.
- Asociar la meta con un cliente y un periodo mediante `tablero_sae`.
- Rechazar desde el flujo de creación una nueva meta cuando ya existe un `tablero_sae` activo para el mismo cliente y mes.
- Listar las metas activas de un cliente dentro de un rango de fechas.
- Servir como referencia para los registros mensuales de calidad asociados por `meta_id`.
- El backend actual no expone endpoints para actualizar, eliminar o restaurar metas.

## Usuarios

- Funcionalmente está dirigido a usuarios que administran el Tablero SAE y definen las metas mensuales del cliente activo.
- En el frontend, la pantalla `/objetivos` exige autenticación y el permiso `view_objetivos_mensuales`.
- Las rutas backend `guardarMeta`, `listarMetas` y `guardarTablero` no tienen middleware de autenticación o autorización declarado en `routes/api.php`.

## Flujo funcional

### Creación

1. El cliente envía `POST /api/guardarMeta` con el mes, los cinco indicadores y `cliente_endpoint_id`.
2. `MetaController::create` convierte `fecha` con `DateTime` y busca en `tablero_sae` un registro activo del mismo año y mes, relacionado con un cliente activo cuyo `cliente_endpoint_id` coincida.
3. Si encuentra un registro, responde HTTP `406` y no crea otra meta.
4. Si no encuentra un registro, busca el cliente. Si no existe, responde HTTP `404`.
5. Si el cliente existe, inserta los indicadores en `meta` y responde con `meta_id` y el `cliente_id` interno.
6. El frontend usa esos identificadores para llamar por separado a `POST /api/guardarTablero`, que inserta `fecha`, `meta_id` y `cliente_id` en `tablero_sae`.

### Consulta

1. El cliente envía `POST /api/listarMetas` con `cliente_endpoint_id` y, opcionalmente, `fecha_inicio` y `fecha_fin`.
2. Si no se reciben fechas, el rango va desde el inicio del día de hace un año hasta el final del día actual.
3. La consulta une `tablero_sae`, `meta` y `clientes`, excluye registros eliminados lógicamente y ordena por `tablero_sae.fecha` de forma descendente.
4. La respuesta incluye `tablero_sae_id`, `meta_id`, `fecha`, los cinco indicadores y las fechas de creación y actualización de la meta.

## Tablas y campos clave

La conexión local configurada en `.env` usa PostgreSQL y tiene `clients, surveys` como `search_path`. La estructura se verificó mediante consultas de solo lectura.

### `clients.meta`

- `meta_id`: `bigint`, clave primaria.
- `cumplimiento`: `integer`, obligatorio; meta de cumplimiento del plan de armado.
- `eficiencia_productiva`: `integer`, obligatorio.
- `calidad`: `integer`, obligatorio; meta de inspección de calidad.
- `desperdicio_me`: `integer`, obligatorio.
- `desperdicio_pp`: `integer`, obligatorio.
- `deleted_at`: marca de eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

La tabla no contiene fecha ni cliente; ambos se resuelven a través de `tablero_sae`.

### `clients.tablero_sae`

- `tablero_sae_id`: `bigint`, clave primaria.
- `fecha`: `timestamp`, obligatorio; determina el periodo de la meta.
- `meta_id`: `bigint`, obligatorio y clave foránea hacia `clients.meta.meta_id`.
- `cliente_id`: `bigint`, obligatorio y clave foránea hacia `clients.clientes.id`.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría temporal.

Es la tabla que vincula una meta con un cliente y con el mes al que aplica.

### `clients.clientes`

- `id`: `bigint`, clave primaria utilizada por `tablero_sae.cliente_id`.
- `cliente_endpoint_id`: `integer`, identificador externo recibido por la API.
- `nombre`, `activo`: datos del cliente.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría temporal.

### `clients.calidad`

- `calidad_id`: `bigint`, clave primaria.
- `checklist`, `inspeccion`: `integer` y anulables; resultados mensuales registrados posteriormente.
- `meta_id`: `bigint`, obligatorio y clave foránea hacia `clients.meta.meta_id`.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría temporal.

`meta.calidad` es el valor objetivo, mientras que `calidad.checklist` y `calidad.inspeccion` son resultados asociados; no representan el mismo dato.

## Archivos relevantes

### Backend principal

- `routes/api.php`: registra `POST /api/guardarMeta` y `POST /api/listarMetas`.
- `app/Http/Controllers/MetaController.php`: valida, crea y lista metas.
- `app/Models/Meta.php`: configura la tabla, clave primaria, campos asignables, eliminación lógica y relaciones.
- `database/migrations/2024_10_21_211028_create_meta_table.php`: define la tabla `meta`.

### Backend relacionado

- `app/Http/Controllers/Tablero_SaeController.php`: crea la asociación entre periodo, meta y cliente mediante `POST /api/guardarTablero`.
- `app/Models/Tablero_Sae.php`: representa `tablero_sae` y su relación con `Meta`.
- `database/migrations/2024_10_21_204217_create_tablero_sae_table.php`: define `tablero_sae` y sus claves foráneas.
- `app/Models/Cliente.php`: resuelve el cliente por `cliente_endpoint_id`.
- `app/Http/Controllers/CalidadController.php`: consume la meta mensual para crear y listar resultados de calidad.
- `app/Models/Calidad.php`: relaciona resultados de calidad con `Meta`.
- `database/migrations/2024_10_30_212547_create_calidad_table.php`: define `calidad` y su clave foránea.

### Frontend consumidor

- `Modulo-clientes-Frontend/components/objetivos/FormObjetivosMen.vue`: formulario mensual y validación visual de porcentajes.
- `Modulo-clientes-Frontend/composables/objetivos/useObjetivosApi.ts`: coordina las llamadas separadas a `guardarMeta`, `guardarTablero` y `listarMetas`.
- `Modulo-clientes-Frontend/interfaces/objetives.ts`: contrato TypeScript del payload.

## Reglas de negocio

- Una meta se interpreta como mensual usando el año y mes de `tablero_sae.fecha`.
- La existencia se comprueba por combinación de mes y `cliente_endpoint_id`, considerando únicamente `tablero_sae` y clientes sin eliminación lógica.
- Los cinco indicadores son obligatorios y se almacenan como enteros.
- El cliente se recibe mediante su identificador externo y se traduce a `clientes.id` antes de crear `tablero_sae`.
- Las consultas omiten metas, tableros y clientes eliminados lógicamente.
- Los resultados de calidad se asocian directamente a la meta mediante `calidad.meta_id`.
- La creación completa depende de dos operaciones consecutivas: primero `meta` y luego `tablero_sae`.

## Validaciones actuales

### `POST /api/guardarMeta`

- `fecha`: obligatoria y de tipo `string`.
- `cumplimiento`: obligatorio y `integer`.
- `eficienciaProductiva`: obligatorio y `integer`.
- `calidad`: obligatorio y `integer`.
- `desperdicioME`: obligatorio y `integer`.
- `desperdicioPP`: obligatorio y `integer`.
- `cliente_endpoint_id`: obligatorio y `integer`.
- Responde HTTP `422` ante errores de validación, `404` si el cliente no existe, `406` si ya hay un tablero para el cliente y mes, y `500` ante otros errores.

### `POST /api/listarMetas`

- `cliente_endpoint_id`: obligatorio y `integer`.
- `fecha_inicio`, `fecha_fin`: opcionales y deben ser fechas válidas.
- Responde HTTP `422` ante filtros inválidos y `500` ante otros errores.

### `POST /api/guardarTablero`

- `fecha`: obligatoria y de tipo `string`.
- `meta_id`, `cliente_id`: obligatorios y `integer`.
- Las claves foráneas de la base exigen que ambos identificadores existan.

## Dependencias

- Laravel: validación de solicitudes, Eloquent, Query Builder, `Carbon`, respuestas JSON y `SoftDeletes`.
- PHP `DateTime`: normalización del mes recibido durante la creación.
- PostgreSQL: esquema `clients`, claves primarias y claves foráneas.
- Módulo de clientes: traducción de `cliente_endpoint_id` a `clientes.id`.
- Módulo Tablero SAE: asociación de la meta con periodo y cliente.
- Módulo de calidad: resultados mensuales vinculados a `meta_id`.
- Frontend del módulo de objetivos: completa el flujo de creación con la segunda petición a `guardarTablero`.

## Riesgos

- La creación de `meta` y `tablero_sae` no es atómica; una falla en la segunda petición puede dejar una meta sin cliente ni periodo.
- La comprobación de duplicados y la inserción están separadas y la base no tiene una restricción única por cliente y mes; solicitudes concurrentes pueden crear duplicados.
- Las rutas backend están públicas en `routes/api.php`, aunque la interfaz aplique autenticación y permiso.
- El backend acepta cualquier entero para los indicadores y no impone el rango porcentual mostrado por el frontend.
- `fecha` se valida solo como texto en los endpoints de creación y su interpretación depende de `DateTime` o del motor de base de datos.
- Las consultas por mes usan `LIKE 'YYYY-MM%'` sobre una columna `timestamp`, lo que depende de conversión implícita y puede impedir el uso eficiente de índices.
- La base solo presenta los índices de clave primaria en estas tablas; las búsquedas y uniones por fecha, cliente y claves foráneas pueden degradarse al crecer el volumen.
- Las respuestas de error general exponen el mensaje interno de la excepción.

## Consideraciones

- Usar los nombres de payload camelCase de la API (`eficienciaProductiva`, `desperdicioME`, `desperdicioPP`) y mapearlos a las columnas snake_case correspondientes.
- Mantener la diferencia entre la meta de calidad (`meta.calidad`) y los resultados mensuales (`calidad.checklist`, `calidad.inspeccion`).
- Cualquier cambio en la creación debe revisar conjuntamente `MetaController`, `Tablero_SaeController` y el consumidor frontend.
- Conservar los filtros de eliminación lógica en listados y validaciones de existencia.
