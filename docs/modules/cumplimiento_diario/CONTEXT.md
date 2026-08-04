# Contexto - Cumplimiento diario

## Objetivo

Administrar la producción y los indicadores operativos diarios del Tablero SAE para cada cliente. El módulo crea el valor de producción planificada de un día y permite completar o modificar posteriormente la producción modificada y los indicadores de cumplimiento del plan de armado, calidad y desperfectos.

## Alcance

- Crear un registro diario a partir de la producción planificada.
- Impedir desde el flujo de creación un segundo registro activo para el mismo cliente y fecha.
- Asociar cada registro diario con la meta mensual del cliente mediante `tablero_sae`.
- Actualizar la producción planificada.
- Registrar o actualizar la producción modificada.
- Registrar o actualizar en bloque los cuatro indicadores diarios.
- Listar los registros diarios de un cliente dentro de un rango de fechas.
- Servir como entidad padre de los accidentes relacionados mediante `objetivos_id`.
- El backend actual no expone endpoints para eliminar, restaurar o consultar individualmente un registro diario.

## Usuarios

- Funcionalmente está dirigido a usuarios que registran y consultan la operación diaria del cliente activo.
- En el frontend, las pantallas `/objetivos/diarios` y `/objetivos/diariosTable` requieren autenticación y el módulo corresponde al permiso `view_objetivos_diarios`.
- Las rutas backend `guardarObjetivos`, `listarObjetivos` y `actualizarObjetivos` no tienen middleware de autenticación o autorización declarado en `routes/api.php`.

## Flujo funcional

### Creación de producción planificada

1. El frontend envía `POST /api/guardarObjetivos` con fecha, identificador externo del cliente en el campo `cliente_id` y producción planificada. Los demás valores se envían nulos.
2. `ObjetivoController::create` convierte la fecha con `DateTime` y obtiene tanto el día (`YYYY-MM-DD`) como el mes (`YYYY-MM`).
3. Busca un `tablero_sae` del cliente para ese mes; este registro aporta la relación con la meta mensual.
4. Consulta si el cliente ya tiene un objetivo activo para la misma fecha.
5. Si ya existe, responde HTTP `422`. Si no existe la meta mensual, responde HTTP `404`.
6. Si ambas comprobaciones permiten continuar, inserta el registro en `objetivos` y lo relaciona mediante `tablero_sae_id`.

### Actualización

1. El frontend envía `POST /api/actualizarObjetivos` con fecha, cliente y uno de estos grupos de datos:
   - `planificada`;
   - `modificada`;
   - `plan_armado`, `calidad`, `desperfecto_me` y `desperfecto_pp`.
2. `ObjetivoController::update` busca el registro por fecha y `cliente_endpoint_id` a través de `tablero_sae`.
3. Si no encuentra el registro responde HTTP `404`.
4. El controlador elige el primer grupo con valor evaluado como verdadero, en este orden: planificada, modificada e indicadores.
5. Guarda los cambios y responde HTTP `200`; si no detecta ningún valor responde HTTP `404` con `success: false`.

### Consulta

1. El frontend envía `POST /api/listarObjetivos` con `cliente_endpoint_id` y fechas opcionales.
2. Sin fechas, el rango predeterminado va desde el inicio del día de hace un mes hasta el final del día actual.
3. La consulta une `objetivos`, `tablero_sae` y `clientes`, y excluye registros eliminados lógicamente de esas tres tablas.
4. Los resultados se ordenan por `objetivos.fecha` de forma descendente.
5. La respuesta incluye identificador, fecha, producción planificada y modificada, cuatro indicadores y fechas de auditoría.

## Tablas y campos clave

La conexión local configurada en `.env` usa PostgreSQL con `clients, surveys` como `search_path`. La estructura y las relaciones se verificaron mediante consultas de solo lectura.

### `clients.objetivos`

- `objetivos_id`: `bigint`, clave primaria.
- `fecha`: `timestamp`, obligatoria; identifica el día operativo.
- `planificada`: `integer`, obligatoria; producción inicialmente prevista.
- `modificada`: `integer`, anulable; ajuste posterior de producción.
- `plan_armado`: `integer`, anulable; cumplimiento diario del plan de armado.
- `calidad`: `integer`, anulable; indicador diario de calidad.
- `desperfecto_me`: `integer`, anulable; indicador de desperfectos M.E.
- `desperfecto_pp`: `integer`, anulable; indicador de desperfectos P.P.
- `tablero_sae_id`: `bigint`, obligatorio y clave foránea hacia `clients.tablero_sae.tablero_sae_id`.
- `deleted_at`: eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

### `clients.tablero_sae`

- `tablero_sae_id`: clave primaria utilizada por `objetivos`.
- `fecha`: determina el mes de la meta asociada.
- `meta_id`: clave foránea hacia `clients.meta.meta_id`.
- `cliente_id`: clave foránea hacia `clients.clientes.id`.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

El tablero vincula cada objetivo diario con el cliente y con la meta mensual aplicable.

### `clients.meta`

- `meta_id`: clave primaria relacionada desde `tablero_sae`.
- `cumplimiento`, `eficiencia_productiva`, `calidad`, `desperdicio_me`, `desperdicio_pp`: valores objetivo mensuales.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

Los valores de `objetivos` son resultados diarios; los valores de `meta` son referencias mensuales y no deben confundirse.

### `clients.clientes`

- `id`: clave primaria relacionada con `tablero_sae.cliente_id`.
- `cliente_endpoint_id`: identificador externo usado para crear, actualizar y listar registros.
- `nombre`, `activo`: información y estado del cliente.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

### `clients.accidentes`

- `accidentes_id`: `bigint`, clave primaria.
- `tipo_accidente`: `varchar`, obligatorio.
- `cantidad`: `integer`, obligatoria.
- `objetivos_id`: `bigint`, obligatorio y clave foránea hacia `clients.objetivos.objetivos_id`.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

Los accidentes son un flujo relacionado que depende del registro diario, pero se crean mediante `AccidentesController` y `POST /api/guardarAccidente`.

## Archivos relevantes

### Backend principal

- `routes/api.php`: registra `guardarObjetivos`, `listarObjetivos` y `actualizarObjetivos`.
- `app/Http/Controllers/ObjetivoController.php`: implementa creación, actualización y listado.
- `app/Models/Objetivo.php`: configura la tabla, clave primaria, campos asignables, eliminación lógica y relación con `Tablero_Sae`.
- `app/Models/Tablero_Sae.php`: relaciona el tablero con objetivos, meta y cliente.
- `database/migrations/2024_11_06_151819_create_objetivos_table.php`: define `objetivos` y su clave foránea.
- `database/migrations/2024_10_21_204217_create_tablero_sae_table.php`: define la relación mensual con meta y cliente.

### Backend relacionado

- `app/Models/Meta.php`: representa las metas mensuales asociadas al tablero.
- `app/Models/Cliente.php`: representa el cliente resuelto mediante `cliente_endpoint_id`.
- `app/Http/Controllers/AccidentesController.php`: crea accidentes asociados a un objetivo diario.
- `app/Models/Accidente.php`: representa `accidentes` y su relación con `Objetivo`.
- `database/migrations/2024_11_06_151906_create_accidentes_table.php`.
- `database/migrations/2024_10_21_211028_create_meta_table.php`.
- `database/migrations/2024_06_19_160520_create_clientes_table.php`.

### Frontend consumidor

- `Modulo-clientes-Frontend/pages/objetivos/diarios.vue`: pantalla de captura.
- `Modulo-clientes-Frontend/pages/objetivos/diariosTable.vue`: consulta por rango de fechas.
- `Modulo-clientes-Frontend/components/objetivos/FormProduccion.vue`: creación de planificada y actualización de modificada.
- `Modulo-clientes-Frontend/components/objetivos/FormIndicadores.vue`: actualización de indicadores.
- `Modulo-clientes-Frontend/composables/objetivos/useObjetivosApi.ts`: consume los tres endpoints.
- `Modulo-clientes-Frontend/composables/objetivos/datosObjetivos.ts`: objeto compartido y validaciones auxiliares.
- `Modulo-clientes-Frontend/interfaces/objetives.ts`: contrato TypeScript.

## Rutas y validaciones actuales

### `POST /api/guardarObjetivos` → `ObjetivoController::create`

- `fecha`: requerida, `string`.
- `cliente_id`: requerido, `integer`; pese al nombre, contiene `cliente_endpoint_id`.
- `planificada`: requerida, `integer`.
- `modificada`: opcional, anulable, `integer`.
- `plan_armado`: opcional, anulable, `integer`.
- `calidad`: opcional, anulable, `integer`.
- `desperfecto_me`: opcional, anulable, `integer`.
- `desperfecto_pp`: opcional, anulable, `integer`.

### `POST /api/actualizarObjetivos` → `ObjetivoController::update`

- `fecha`: requerida, `string`.
- `cliente_id`: requerido, `integer`; contiene el identificador externo.
- Todos los campos numéricos son opcionales, anulables y `integer`.

### `POST /api/listarObjetivos` → `ObjetivoController::list`

- `cliente_endpoint_id`: requerido, `integer`.
- `fecha_inicio`, `fecha_fin`: opcionales y `date`.

## Reglas de negocio

- Debe existir una meta mensual del cliente representada por `tablero_sae` antes de crear producción diaria.
- El flujo de creación admite un solo objetivo activo por cliente y fecha.
- `planificada` es el único valor operativo obligatorio al crear.
- `modificada` y los cuatro indicadores pueden permanecer nulos y completarse después.
- La actualización identifica el registro por fecha y cliente, no por `objetivos_id`.
- Una petición de actualización modifica solamente el primer grupo reconocido según la prioridad del controlador.
- Los cuatro indicadores se actualizan juntos cuando `plan_armado` activa esa rama.
- El frontend impide valores negativos de producción y limita los indicadores entre 0 y 100.
- Los registros y listados utilizan eliminación lógica.

## Dependencias

- Laravel: validación de solicitudes, Eloquent, Query Builder, respuestas JSON y `SoftDeletes`.
- PHP `DateTime`: normalización de día y mes durante la creación.
- `Carbon`: construcción del rango predeterminado del listado.
- PostgreSQL: esquema `clients` y claves foráneas.
- Módulos de metas, Tablero SAE y clientes.
- Frontend de objetivos: separa creación de planificada, actualización de modificada y actualización de indicadores.
- Módulo de accidentes: puede asociar eventos posteriores al registro diario.

## Riesgos

- La base no tiene una restricción única por cliente y día; la comprobación previa no evita duplicados concurrentes.
- La actualización usa evaluación booleana y no permite registrar correctamente el valor entero `0` en planificada, modificada o `plan_armado`.
- Cuando se activa la rama de indicadores, los otros tres campos pueden sobrescribirse con `null` porque son opcionales en la validación.
- La búsqueda de actualización no filtra explícitamente registros eliminados de `objetivos`, `tablero_sae` o `clientes` antes de elegir el primer resultado.
- La creación y actualización pueden seleccionar el primer registro si existen varios tableros mensuales o varios objetivos para la misma combinación.
- El backend no rechaza producciones negativas ni indicadores fuera de 0–100.
- `fecha` se valida solo como texto en creación y actualización.
- Las rutas backend carecen de autenticación y autorización.
- Las consultas mensuales y diarias usan `LIKE` sobre columnas `timestamp` y las tablas verificadas solo tienen índices de clave primaria.
- Las respuestas de error general exponen el mensaje interno de la excepción.

## Consideraciones

- Mantener clara la diferencia entre el identificador externo recibido como `cliente_id` y la clave interna `clientes.id` almacenada en `tablero_sae`.
- Tratar la producción (`planificada`, `modificada`) como cantidades y los cuatro indicadores como porcentajes solo después de confirmar sus escalas funcionales.
- Cualquier cambio del contrato de actualización debe revisarse en ambos formularios frontend, ya que comparten el mismo endpoint.
- Derivar el tablero desde cliente y mes con cardinalidad inequívoca antes de asociar el objetivo.
- Conservar el aislamiento por cliente y los filtros de eliminación lógica en todas las operaciones.
