# Contexto - Programación detallada

## Objetivo

Administrar la programación semanal detallada por cliente, SKU y producto. El submódulo permite previsualizar una plantilla Excel, validar sus filas contra los catálogos operativos, guardar o reemplazar la programación de un usuario para una semana ISO y consultar las programaciones almacenadas con sus detalles.

## Alcance

- Seleccionar un año y una semana ISO desde el frontend.
- Cargar archivos Excel `.xlsx` o `.xls` de hasta 10 MB.
- Leer la hoja `Detalle Semana` y localizar las columnas `Nombre Centro`, `APO:Producto` y `Total Semana`.
- Ignorar filas completamente vacías, totales representados por `-` y totales numéricos iguales a cero.
- Resolver cada centro del archivo contra `public.cliente`.
- Comprobar que exista una actividad no eliminada para la combinación de cliente y SKU.
- Previsualizar las filas válidas antes de guardarlas.
- Crear una cabecera semanal y sus detalles en una transacción.
- Solicitar confirmación antes de reemplazar los detalles de una programación existente.
- Listar las cabeceras activas y mostrar sus detalles activos.
- El submódulo no distribuye valores por día, no registra ejecución real y no calcula cumplimiento.

## Usuarios

- Usuarios de planeación u objetivos que cargan y consultan programaciones semanales.
- El frontend exige la sesión mediante el middleware `auth` y usa el permiso de menú `view_scheduled_detail` para mostrar el acceso.
- El nombre de usuario se toma de la cookie `usuario` en el frontend y se envía como parte de las solicitudes.
- Las tres rutas backend del submódulo no tienen actualmente middleware de autenticación o autorización declarado en `routes/api.php`.

## Flujo funcional

### Previsualización del Excel

1. `POST /api/scheduled-detail/preview` recibe `archivo`, `year`, `week` y `username` como `multipart/form-data`.
2. El backend comprueba que la semana exista en el año ISO indicado y calcula el lunes y el domingo correspondientes.
3. PhpSpreadsheet busca una hoja cuyo nombre normalizado sea `Detalle Semana` y la carga en modo de solo datos.
4. Se recorre la hoja hasta encontrar una fila que contenga las tres columnas requeridas. La comparación de encabezados ignora mayúsculas, espacios repetidos y espacios alrededor de `:`.
5. Cada fila no vacía y con total distinto de cero debe contener nombre de centro, `APO:Producto` y un total entero.
6. `APO:Producto` se divide por la primera `/`; la parte izquierda se interpreta como SKU y la derecha como producto.
7. El centro se resuelve primero por nombre normalizado exacto, después mediante alias conocidos y finalmente mediante una coincidencia parcial que solo se acepta si produce un único cliente.
8. La actividad se busca por `client_id` y `codigo_cobro = SKU`, excluyendo actividades con `deleted_at`. Se prefiere una actividad cuyo nombre termine exactamente en el producto; si no existe esa coincidencia, se usa la primera actividad candidata ordenada por identificador.
9. Si alguna fila presenta errores, se rechaza toda la previsualización con HTTP `422` y errores asociados al número real de fila del Excel.
10. Si todas las filas procesadas son válidas, se devuelven las fechas de la semana, cliente, SKU, producto, `activity_id` y total semanal. La previsualización no escribe en la base de datos.

Alias de clientes codificados en el controlador:

- `cedi madrid` → `gaseosas lux madrid`.
- `calle 80` → `gaseosas lux calle 80`.
- `calle 80 exportacion` → `gaseosas lux calle 80`.
- `postobon malambo exportacion` → `postobon malambo`.
- `lux sur` → `gaslux sur`.

### Guardado y reemplazo

1. `POST /api/scheduled-detail/store` recibe `year`, `week`, `username`, un arreglo no vacío `values` y, opcionalmente, `replace_existing`.
2. Se vuelve a validar la existencia de la semana ISO y que no se repita dentro de la solicitud la combinación exacta `client_id + sku + producto`.
3. Dentro de una transacción se busca una cabecera activa por `username`, `year` y `week_number`, aplicando bloqueo de fila cuando existe.
4. Si ya existe y `replace_existing` no es verdadero, se responde HTTP `409` con `requires_confirmation = true`.
5. Si no existe, se crea `scheduled_detail` con las fechas calculadas, `notes = null` y el usuario recibido.
6. Si existe y se confirmó el reemplazo, sus detalles activos se eliminan lógicamente.
7. Se crean los nuevos `weekly_scheduled_detail` con cliente, total semanal, SKU, producto, usuario y `notes = null`.
8. Una creación responde HTTP `201`; un reemplazo responde HTTP `200`. Cualquier excepción revierte la transacción.

El `activity_id` devuelto por la previsualización no forma parte del contrato de guardado y no se almacena en las tablas del submódulo.

### Consulta

1. `GET /api/scheduled-detail` obtiene todas las cabeceras no eliminadas.
2. Carga sus detalles no eliminados y hace `LEFT JOIN` con `public.cliente` para obtener `client_name`.
3. Ordena las cabeceras de la fecha inicial más reciente a la más antigua y, en caso de empate, por identificador descendente.
4. El frontend presenta una tabla de cabeceras y un modal con los detalles de la programación seleccionada.
5. La consulta actual no recibe paginación ni filtros en el backend; los filtros visibles se aplican sobre los datos ya descargados.

## Tablas y campos clave

La estructura descrita a continuación se verificó mediante consultas de solo lectura a PostgreSQL. La conexión usa `clients,surveys` como `search_path`, mientras que los catálogos consultados se califican explícitamente con el esquema `public`.

### `clients.scheduled_detail`

- `scheduled_detail_id`: `bigint`, clave primaria autoincremental.
- `year`: `integer`, obligatorio; año de la semana ISO.
- `week_number`: `smallint`, obligatorio; restringido entre `1` y `53`.
- `week_start_date`: `date`, obligatorio; debe ser lunes.
- `week_end_date`: `date`, obligatorio; debe ser domingo y estar exactamente seis días después de `week_start_date`.
- `notes`: `text`, anulable; el flujo actual siempre guarda `null`.
- `username`: `varchar`, obligatorio; identifica al usuario atribuido a la carga.
- `deleted_at`: eliminación lógica aplicada por Eloquent.
- `created_at`, `updated_at`: auditoría temporal.

La clave funcional usada por el controlador es `username + year + week_number`. En la base local revisada solo está presente el índice de clave primaria; el índice único parcial definido por la migración del submódulo no está materializado actualmente.

### `clients.weekly_scheduled_detail`

- `weekly_scheduled_detail_id`: `bigint`, clave primaria autoincremental.
- `scheduled_detail_id`: `bigint`, obligatorio; clave foránea hacia `clients.scheduled_detail` con actualización en cascada y borrado físico restringido.
- `client_id`: `integer`, obligatorio; clave foránea hacia `public.cliente.cliente_id` con actualización en cascada y borrado físico restringido.
- `weekly_total`: `bigint`, obligatorio; la base exige un valor mayor o igual a cero.
- `sku`: `varchar`, anulable en base, aunque el endpoint de guardado lo exige.
- `product`: `varchar`, anulable en base, aunque el endpoint de guardado lo exige bajo el nombre `producto`.
- `notes`: `text`, anulable; el flujo actual siempre guarda `null`.
- `username`: `varchar`, obligatorio; se copia desde la cabecera recibida.
- `deleted_at`: eliminación lógica aplicada por Eloquent.
- `created_at`, `updated_at`: auditoría temporal.

No existe una restricción única de base para `scheduled_detail_id + client_id + sku + product`.

### `public.cliente`

- `cliente_id`: `integer`, clave primaria usada como `weekly_scheduled_detail.client_id`.
- `nombre`: `varchar`, obligatorio; valor usado para resolver `Nombre Centro` y presentar el cliente en la consulta.
- `activo`: `varchar`; existe en el catálogo, pero el submódulo no lo filtra.
- `deleted_at`: eliminación lógica; existe en el catálogo, pero la resolución y el listado del submódulo no lo filtran.

### `public.actividad`

- `actividad_id`: `varchar`, clave de la actividad devuelta durante la previsualización.
- `cliente_id`: `integer`, cliente al que pertenece la actividad.
- `codigo_cobro`: `varchar`, comparado exactamente con el SKU del Excel.
- `nombre`: `varchar`, usado para intentar distinguir el producto entre actividades candidatas.
- `activo`: `varchar`; no se filtra en la previsualización.
- `deleted_at`: las actividades eliminadas lógicamente sí se excluyen.

`clients.weekly_scheduled_detail` no tiene una columna ni una clave foránea hacia `public.actividad`; la actividad solo interviene como validación previa.

## Archivos relevantes

### Backend

- `app/Http/Controllers/ScheduledDetailController.php`: previsualización, normalización, resolución de clientes y actividades, guardado, reemplazo y listado.
- `app/Models/ScheduledDetail.php`: modelo de la cabecera, `SoftDeletes`, campos asignables y relación con detalles.
- `app/Models/WeeklyScheduledDetail.php`: modelo del detalle, `SoftDeletes`, campos asignables y relación con la cabecera.
- `routes/api.php`: registra los tres endpoints del submódulo.
- `routes/web.php`: no registra rutas de Programación detallada; las rutas funcionales están exclusivamente en `routes/api.php`.
- `database/migrations/2026_08_01_000000_add_active_schedule_user_week_unique_index.php`: define un índice único parcial para cabeceras activas por usuario, año y semana.
- `config/database.php`: configura PostgreSQL y el `search_path` usado por los modelos no calificados.
- `composer.json` y `composer.lock`: declaran `phpoffice/phpspreadsheet`, utilizado para leer los archivos.

El repositorio no contiene migraciones de creación para `scheduled_detail` ni `weekly_scheduled_detail`; solo contiene la migración posterior del índice único.

### Frontend consumidor

- `Modulo-clientes-Frontend/pages/objetivos/programacion-detallada.vue`: selección de año/semana, carga, previsualización, búsqueda, guardado y confirmación de reemplazo.
- `Modulo-clientes-Frontend/pages/objetivos/programacion-detallada-table.vue`: consulta de cabeceras y modal de detalles.
- `Modulo-clientes-Frontend/composables/objetivos/useScheduledDetailApi.ts`: contratos TypeScript y consumo de los tres endpoints.
- `Modulo-clientes-Frontend/composables/objetivos/ScheduledDetailData.ts`: pestañas, encabezados y atributos de las tablas.
- `Modulo-clientes-Frontend/composables/menuItems.ts`: entrada de menú asociada a `view_scheduled_detail`.

## Reglas de negocio

- El periodo se expresa como año y número de semana ISO; la semana comienza el lunes y termina el domingo.
- El backend rechaza la semana `53` cuando el año seleccionado solo tiene `52` semanas ISO.
- La plantilla debe contener una hoja llamada `Detalle Semana` y las tres columnas requeridas.
- Las filas vacías, `Total Semana = 0` y `Total Semana = -` no forman parte de la previsualización.
- Cada valor importado debe corresponder a un cliente resoluble y a por lo menos una actividad no eliminada con el mismo cliente y SKU.
- Dentro de una misma solicitud de guardado no puede repetirse exactamente cliente, SKU y producto.
- El controlador considera una sola cabecera activa por usuario, año y semana.
- Reemplazar una programación conserva la cabecera y elimina lógicamente sus detalles anteriores antes de insertar los nuevos.
- Las cabeceras y los detalles usan `SoftDeletes`; las consultas ordinarias excluyen registros eliminados.
- El reemplazo es total, no incremental: todos los detalles activos anteriores son sustituidos.
- `username` es un dato suministrado por el consumidor y no se deriva de la identidad autenticada en el backend.

## Validaciones actuales

### `POST /api/scheduled-detail/preview`

- `archivo`: obligatorio, archivo, MIME compatible con `xlsx` o `xls`, máximo `10240` KB.
- `year`: obligatorio, entero entre `2000` y `2100`.
- `week`: obligatorio, entero entre `1` y `53` y existente en el año ISO indicado.
- `username`: obligatorio, string de máximo `255` caracteres.
- Hoja: nombre normalizado igual a `Detalle Semana`.
- Columnas: `Nombre Centro`, `APO:Producto` y `Total Semana`.
- Fila: centro y `APO:Producto` obligatorios y de tipo string; total obligatorio y entero.
- `APO:Producto`: debe contener SKU y producto no vacíos separados por `/`.
- Cliente: debe resolverse por nombre, alias o coincidencia parcial no ambigua.
- Actividad: debe existir una candidata no eliminada para cliente y SKU.

### `POST /api/scheduled-detail/store`

- `year`: obligatorio, entero entre `2000` y `2100`.
- `week`: obligatorio, entero entre `1` y `53` y existente en el año ISO indicado.
- `username`: obligatorio, string de máximo `255` caracteres.
- `replace_existing`: opcional, booleano.
- `values`: obligatorio, arreglo con mínimo una fila.
- `values.*.client_id`: obligatorio, entero.
- `values.*.sku`: obligatorio, string de máximo `255` caracteres.
- `values.*.producto`: obligatorio, string de máximo `255` caracteres.
- `values.*.value`: obligatorio, entero. La regla HTTP no impone mínimo; PostgreSQL sí exige `weekly_total >= 0`.
- Duplicados: se compara la clave exacta `client_id|sku|producto` dentro del arreglo.

### `GET /api/scheduled-detail`

- No recibe parámetros ni aplica validaciones de entrada.
- No pagina ni filtra por usuario, cliente, año o semana en el backend.

## Dependencias

- Laravel 11: validación, Eloquent, Query Builder, transacciones, bloqueo de filas, respuestas JSON y registro de errores.
- Carbon: cálculo y validación de semanas ISO.
- PhpSpreadsheet `^5.9`: inspección y lectura del Excel.
- PostgreSQL: esquemas `clients` y `public`, restricciones, claves foráneas y soft deletes.
- Catálogo `public.cliente`: resolución de centros y nombres de clientes.
- Catálogo `public.actividad`: validación de cliente, SKU y producto durante la previsualización.
- Frontend Nuxt/Vue: interfaz de carga, consulta, confirmación y filtrado local.
- Cookie frontend `usuario`: origen actual del nombre atribuido a la carga.
- Permiso frontend `view_scheduled_detail`: controla la visibilidad del acceso en el menú.

## Riesgos

- Las rutas backend carecen de autenticación y autorización, aunque las páginas frontend sí están protegidas.
- El consumidor puede enviar cualquier `username`; el backend no comprueba que corresponda a la sesión.
- La migración del índice único consta como ejecutada en la base local, pero el índice no existe; dos creaciones concurrentes pueden producir cabeceras duplicadas.
- El endpoint de guardado permite enteros negativos y la base los rechaza, convirtiendo un error de validación en una respuesta HTTP `500`.
- El guardado confía en los datos enviados después de la previsualización y no vuelve a validar cliente, actividad, SKU o producto.
- La resolución de clientes puede incluir registros inactivos o eliminados y una coincidencia exacta duplicada conserva silenciosamente el primer identificador.
- Cuando ningún nombre de actividad termina en el producto, se acepta la primera actividad del mismo cliente y SKU; esto puede validar una relación de producto incorrecta.
- El `activity_id` validado no se persiste, por lo que no queda trazabilidad de la actividad que justificó la fila.
- La hoja completa se convierte en un arreglo en memoria; un archivo comprimido dentro del límite puede consumir mucha más memoria al expandirse.
- El listado trae todas las cabeceras y todos sus detalles en una sola solicitud, lo que puede degradarse con el crecimiento de datos.
- La ausencia de migraciones de creación impide reproducir íntegramente las tablas desde el historial de migraciones del repositorio.

## Consideraciones

- No confundir `public.cliente` con `clients.clientes`: este submódulo usa el catálogo singular del esquema `public`.
- `producto` es el nombre del campo en la previsualización y el guardado; se almacena como `product`.
- `value` es el nombre del total en los contratos de previsualización y guardado; se almacena como `weekly_total`.
- El nombre del centro se usa para resolver el cliente, pero no se almacena en el detalle; el listado recupera el nombre vigente mediante `client_id`.
- Los alias están codificados en el controlador y dependen de los nombres del catálogo.
- La comparación del producto contra el final del nombre de la actividad es sensible a mayúsculas, espacios y caracteres del texto.
- Los campos `notes` están disponibles en ambas tablas, pero no participan en la interfaz ni en los contratos actuales.
- El reemplazo no modifica explícitamente la cabecera, por lo que su `updated_at` no representa necesariamente la fecha del último reemplazo de detalles.
- La protección visual por permiso en el frontend no sustituye una política de autorización en los endpoints.

