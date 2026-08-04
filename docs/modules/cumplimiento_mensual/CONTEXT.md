# Contexto - Cumplimiento mensual

## Objetivo

Administrar los resultados mensuales de calidad del Tablero SAE para cada cliente. El módulo registra una calificación de checklist y una calificación de inspección por meta mensual, adjunta evidencias PDF, permite consultar los resultados por rango de fechas y gestiona el listado, descarga y reemplazo de las evidencias.

## Alcance

- Registrar la calificación mensual de checklist.
- Registrar la calificación mensual de inspección.
- Completar posteriormente el segundo resultado cuando la fila de calidad ya contiene uno de los dos valores.
- Verificar si un resultado ya fue registrado para un cliente, mes y tipo de formulario.
- Consultar resultados mensuales por cliente y rango de fechas.
- Recibir evidencias PDF, comprimirlas como ZIP y registrar su ubicación.
- Listar evidencias disponibles y registros cuyo archivo físico ya no existe.
- Extraer y descargar el PDF contenido en una evidencia ZIP.
- Reemplazar evidencias mediante eliminación y carga posterior desde el frontend.
- El backend no expone una operación propia para editar directamente una calificación ya registrada ni para restaurar evidencias eliminadas.

## Usuarios

- Funcionalmente está dirigido a usuarios que registran y consultan resultados mensuales de calidad del cliente activo.
- En el frontend, `/objetivos/calidad` requiere autenticación y corresponde al permiso `view_objetivos_calidad`.
- Las rutas backend de calidad y evidencias no tienen middleware de autenticación o autorización declarado en `routes/api.php`.

## Flujo funcional

### Registro de una calificación con evidencia

1. El usuario selecciona mes, ingresa una calificación y adjunta un PDF en el formulario de checklist o inspección.
2. El frontend envía `POST /api/guardarCalidad` con `fecha`, `cliente_endpoint_id` y uno de los campos `checklist` o `inspeccion`; el otro se envía como `null`.
3. `CalidadController::create` transforma la fecha a año y mes y busca el `tablero_sae` correspondiente al cliente.
4. Si no existe una meta asociada para ese mes, responde HTTP `404`.
5. Si no existe una fila activa de `calidad` para la meta, la crea. Si ya existe, intenta completar el campo que todavía sea nulo; un valor ya registrado produce HTTP `409`.
6. La respuesta satisfactoria entrega `tablero_sae_id`.
7. El frontend envía después `POST /api/guardarArchivo` con el PDF, cliente, tipo de formulario, `tablero_sae_id` y año de archivo.
8. `FileController::saveFileCalidad` comprime el PDF en un ZIP, lo guarda en el disco `evidencias` y crea el registro en `files`.

### Consulta de resultados

1. El frontend envía `POST /api/listarCalidades` con `cliente_endpoint_id` y fechas opcionales.
2. Sin filtros, el backend consulta desde el inicio del día de hace un mes hasta el final del día actual.
3. La consulta une `calidad`, `meta`, `tablero_sae` y `clientes`, añade las evidencias activas mediante una unión izquierda y excluye registros eliminados lógicamente.
4. La respuesta contiene `calidad_id`, `tablero_sae_id`, `fecha`, `checklist`, `inspeccion` y el conteo de evidencias, ordenados por fecha descendente.

### Verificación de valores

1. `POST /api/verificarCalidad` recibe fecha, cliente y un `tipo_formulario` opcional.
2. Localiza la meta del cliente para el mes y revisa la fila de `calidad` asociada.
3. Para `checklist`, devuelve la calificación existente; cualquier otro tipo se trata como inspección.
4. Si no existe meta responde HTTP `404`; si no existe valor que actualizar responde HTTP `200` con `success: false`.

### Gestión de evidencias

- `POST /api/listarArchivos` recorre los tableros del cliente y sus registros activos de `files`. Separa las rutas existentes en `archivos` y las ausentes físicamente en `archivosInexistentes`.
- El tipo mostrado se deduce de la ruta: si contiene `checklist` se presenta como Checklist; en caso contrario, como Inspección sol.
- `POST /api/descargar-pdf` abre el ZIP señalado por `url`, toma su primer archivo y lo transmite como `application/pdf`.
- `POST /api/deleteFile` aplica eliminación lógica al registro y elimina el archivo físico si existe.
- El reemplazo no es un endpoint atómico: el frontend llama primero a `deleteFile` y luego a `guardarArchivo`.

## Tablas y campos clave

La conexión local configurada en `.env` usa PostgreSQL con `clients, surveys` como `search_path`. La estructura se comprobó mediante consultas de solo lectura.

### `clients.calidad`

- `calidad_id`: `bigint`, clave primaria.
- `checklist`: `integer`, anulable; resultado mensual del checklist.
- `inspeccion`: `integer`, anulable; resultado mensual de inspección.
- `meta_id`: `bigint`, obligatorio y clave foránea hacia `clients.meta.meta_id`.
- `deleted_at`: eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

La tabla no almacena directamente cliente ni fecha. Esa información se obtiene desde la meta y su registro de `tablero_sae`.

### `clients.files`

- `files_id`: `bigint`, clave primaria.
- `ruta`: `varchar`, obligatorio; ruta relativa del ZIP en el disco `evidencias`.
- `tipo`: `varchar`, obligatorio; actualmente recibe la extensión del archivo original (`pdf`).
- `tablero_sae_id`: `bigint`, obligatorio y clave foránea hacia `clients.tablero_sae.tablero_sae_id`.
- `deleted_at`: eliminación lógica.
- `created_at`, `updated_at`: auditoría temporal.

La relación con el resultado mensual es indirecta: `files → tablero_sae → meta ← calidad`.

### `clients.meta`

- `meta_id`: clave primaria y enlace con `calidad.meta_id` y `tablero_sae.meta_id`.
- `calidad`: meta porcentual de calidad contra la que puede interpretarse el resultado mensual.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

`meta.calidad` representa el objetivo; `calidad.checklist` y `calidad.inspeccion` representan resultados registrados.

### `clients.tablero_sae`

- `tablero_sae_id`: clave primaria utilizada por `files`.
- `fecha`: periodo mensual consultado y mostrado.
- `meta_id`: clave foránea hacia `meta`.
- `cliente_id`: clave foránea hacia `clientes.id`.
- `deleted_at`, `created_at`, `updated_at`: eliminación lógica y auditoría.

### `clients.clientes`

- `id`: clave primaria relacionada con `tablero_sae.cliente_id`.
- `cliente_endpoint_id`: identificador externo recibido por las APIs.
- `nombre`: se usa para construir el directorio de evidencias.
- `activo`, `deleted_at`: estado funcional y eliminación lógica.

## Almacenamiento de evidencias

- Disco Laravel: `evidencias`.
- Driver: `local`.
- Raíz: `storage/app/evidencias`.
- Visibilidad configurada: `public`.
- Estructura generada: `Calidad/{cliente-sin-espacios}/{year_file}/{tipo_formulario}/{fecha-carga}_{nombre-original-sin-extension}.zip`.
- El ZIP contiene el PDF original con su nombre original.
- La base guarda la ruta relativa del ZIP, aunque la columna `tipo` registra `pdf`.

## Archivos relevantes

### Backend principal

- `routes/api.php`: registra los endpoints de calidad y archivos.
- `app/Http/Controllers/CalidadController.php`: crea, verifica y lista resultados mensuales.
- `app/Models/Calidad.php`: configura `clients.calidad`, sus campos y relación con `Meta`.
- `app/Http/Controllers/FileController.php`: guarda, lista, descarga y elimina evidencias.
- `app/Models/File.php`: configura `clients.files` y su relación con `Tablero_Sae`.
- `config/filesystems.php`: define el disco local `evidencias`.
- `database/migrations/2024_10_30_212547_create_calidad_table.php`: define `calidad` y su clave foránea.
- `database/migrations/2024_10_21_214938_create_files_table.php`: define `files` y su clave foránea.

### Backend relacionado

- `app/Models/Meta.php`: relación de la meta con resultados de calidad.
- `app/Models/Tablero_Sae.php`: relación del periodo y cliente con meta y evidencias.
- `app/Models/Cliente.php`: resolución del cliente externo y nombre del directorio.
- `database/migrations/2024_10_21_211028_create_meta_table.php`.
- `database/migrations/2024_10_21_204217_create_tablero_sae_table.php`.
- `database/migrations/2024_06_19_160520_create_clientes_table.php`.

### Frontend consumidor

- `Modulo-clientes-Frontend/pages/objetivos/calidad.vue`: formularios y tabla de evidencias.
- `Modulo-clientes-Frontend/pages/objetivos/calidadTable.vue`: consulta de resultados por fechas.
- `Modulo-clientes-Frontend/components/objetivos/FormChecklist.vue`.
- `Modulo-clientes-Frontend/components/objetivos/FormCalidad.vue`.
- `Modulo-clientes-Frontend/components/objetivos/ModalUpdateFile.vue`.
- `Modulo-clientes-Frontend/composables/objetivos/useObjetivosApi.ts`: registro, consulta y descarga.
- `Modulo-clientes-Frontend/composables/objetivos/useFilesApi.ts`: reemplazo de evidencias.
- `Modulo-clientes-Frontend/interfaces/objetives.ts`: contratos TypeScript.

## Endpoints y validaciones actuales

### `POST /api/guardarCalidad`

- `fecha`: requerida, `string`.
- `cliente_endpoint_id`: requerido, `integer`.
- `checklist`: opcional, anulable, `integer`.
- `inspeccion`: opcional, anulable, `integer`.

### `POST /api/listarCalidades`

- `cliente_endpoint_id`: requerido, `integer`.
- `fecha_inicio`, `fecha_fin`: opcionales, `date`.

### `POST /api/verificarCalidad`

- `fecha`: requerida, `string`.
- `cliente_endpoint_id`: requerido, `integer`.
- `tipo_formulario`: opcional, anulable, `string`.

### `POST /api/guardarArchivo`

- `archivo`: requerido, archivo con MIME/extensión permitida `pdf`.
- `cliente_endpoint_id`: requerido, `integer`.
- `tipo_formulario`: requerido, `string`.
- `tablero_sae_id`: requerido, `integer`.
- `year_file`: requerido, `string`.

### `POST /api/listarArchivos`

- `cliente_endpoint`: requerido, `integer`.

### `POST /api/descargar-pdf`

- `url`: requerida, `string`.

### `POST /api/deleteFile`

- `url`: requerida, `string`.
- `id`: requerido, `integer`.

## Reglas de negocio

- Debe existir una meta de `tablero_sae` para el cliente y mes antes de registrar cumplimiento mensual.
- Una fila de `calidad` puede contener checklist, inspección o ambos valores.
- Cuando existe una fila activa, el flujo completa solamente el valor que todavía esté nulo; no sobrescribe directamente una calificación existente.
- El frontend maneja los tipos `checklist` e `inspeccion_sol` y exige calificaciones enteras entre 0 y 100.
- El frontend exige una evidencia PDF para cada registro nuevo.
- Cada evidencia se vincula al `tablero_sae_id`, no directamente al `calidad_id` ni a un campo de resultado.
- Los listados funcionales usan el `cliente_endpoint_id` del cliente activo.
- Los modelos `Calidad` y `File` usan `SoftDeletes`.
- La descarga presupone que cada ZIP contiene al menos un archivo y transmite el primero como PDF.

## Dependencias

- Laravel: validación, Eloquent, Query Builder, `Storage`, respuestas JSON y `SoftDeletes`.
- PHP `DateTime`: normalización del mes y fecha del nombre de archivo.
- Extensión PHP `ZipArchive`: creación y lectura de evidencias comprimidas.
- PostgreSQL: esquema `clients` y relaciones entre calidad, meta, tablero y cliente.
- Sistema de archivos del servidor: disco local `evidencias` y directorio temporal del sistema.
- Módulos de metas, Tablero SAE y clientes.
- Frontend del módulo de objetivos para coordinar las operaciones secuenciales.

## Riesgos

- El guardado de la calificación, del ZIP y del registro `files` no es atómico; pueden quedar calificaciones sin evidencia, archivos físicos sin registro o registros sin archivo físico.
- El reemplazo elimina primero el registro y archivo anterior; una falla al subir el nuevo puede causar pérdida de evidencia.
- La base no garantiza una sola fila activa de `calidad` por `meta_id` ni una sola evidencia por tablero y tipo.
- El backend no limita las calificaciones a 0–100 y permite que `checklist` e `inspeccion` lleguen ambos nulos.
- La selección del campo a completar depende del valor booleano de `checklist`; el valor válido `0` se interpreta como ausencia.
- `tipo_formulario`, `year_file`, `url`, `id` y `tablero_sae_id` no se validan contra una relación común de cliente, periodo y evidencia.
- El tipo visible de evidencia se infiere a partir del texto de la ruta y no de un campo de dominio validado.
- Las rutas backend no están protegidas, incluyendo descarga y eliminación de archivos.
- Los mensajes HTTP `500` exponen detalles internos de excepciones.
- Las búsquedas mensuales usan `LIKE` sobre una columna `timestamp` y las tablas verificadas solo tienen índices de clave primaria.

## Consideraciones

- Coordinar cualquier cambio de tipos entre los valores `checklist` e `inspeccion_sol`, los nombres de directorio y la presentación en el frontend.
- Distinguir siempre la meta de calidad (`meta.calidad`) de los resultados (`calidad.checklist`, `calidad.inspeccion`).
- Mantener sincronizados el archivo físico y su registro en `clients.files` durante carga, reemplazo y eliminación.
- Derivar el periodo y el cliente desde relaciones verificadas, no confiar únicamente en identificadores y rutas enviados por el consumidor.
- Conservar los filtros de eliminación lógica en consultas de resultados y evidencias.
