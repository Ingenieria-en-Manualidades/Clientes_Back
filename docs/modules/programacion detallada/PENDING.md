# Pendientes - Programación detallada

## Bugs

1. Materializar y verificar el índice único de cabeceras activas.
   - La migración `2026_08_01_000000_add_active_schedule_user_week_unique_index` figura como ejecutada en la base local.
   - `to_regclass('clients.scheduled_detail_active_user_week_unique')` devuelve `null` y `pg_indexes` solo muestra la clave primaria.
   - La revisión del 2026-08-19 no encontró grupos duplicados activos, pero una carrera entre dos creaciones todavía puede generarlos.

2. Rechazar totales negativos como error de validación.
   - `previewExcel` y `store` aceptan cualquier entero.
   - PostgreSQL exige `weekly_total >= 0`.
   - Un valor negativo supera la validación HTTP y falla durante la inserción, produciendo una respuesta HTTP `500` en vez de `422`.

3. Proteger los tres endpoints con autenticación y autorización backend.
   - El frontend usa middleware `auth` y el menú espera `view_scheduled_detail`.
   - `routes/api.php` publica previsualización, guardado y listado sin middleware.
   - Un consumidor directo puede consultar datos, cargar archivos o reemplazar programaciones sin pasar por las protecciones del frontend.

4. Evitar la suplantación de `username`.
   - La cookie del frontend se copia al payload.
   - El backend confía en el string recibido y no lo compara con una identidad autenticada.
   - La unicidad y la auditoría de las programaciones dependen de ese valor manipulable.

5. Revalidar las filas al guardar.
   - `store` no confirma que `client_id` exista, esté habilitado o corresponda al SKU/producto enviado.
   - Tampoco repite la resolución de actividad realizada en la previsualización.
   - Una solicitud construida manualmente puede omitir la previsualización y guardar combinaciones arbitrarias; la clave foránea solo garantiza la existencia física del cliente.

6. Corregir la selección ambigua de actividades.
   - Si ninguna actividad candidata termina en el texto de producto, se usa la primera actividad del mismo cliente y SKU.
   - La coincidencia por sufijo es sensible a mayúsculas y espacios.
   - El archivo puede validarse contra una actividad cuyo producto no corresponda realmente.

7. Excluir clientes y actividades inactivos.
   - La resolución de clientes no filtra `deleted_at` ni `activo`.
   - La resolución de actividades filtra `deleted_at`, pero no `activo`.
   - El listado también puede recuperar el nombre de un cliente eliminado mediante el `LEFT JOIN`.

8. Mostrar las columnas faltantes reportadas por el backend.
   - Cuando faltan encabezados, la API devuelve `missing_columns`.
   - `useScheduledDetailApi.ts` no traslada ese campo al resultado consumido por la página.
   - El usuario solo ve el mensaje general y no la lista de columnas requeridas ausentes.

9. Actualizar la auditoría de la cabecera durante un reemplazo.
   - El reemplazo elimina y crea detalles, pero no guarda cambios en `scheduled_detail`.
   - `scheduled_detail.updated_at` puede conservar la fecha original aunque el contenido haya sido reemplazado después.

10. Manejar de forma explícita una plantilla sin filas importables.
    - Una hoja con encabezados válidos y solo filas vacías, con `-` o con total cero responde como previsualización exitosa con `values = []`.
    - El frontend oculta el botón de guardado, pero no explica que no encontró valores importables.

## Mejoras

1. Definir una relación persistente con la actividad.
   - La previsualización calcula y devuelve `activity_id`, pero el frontend lo elimina del payload de guardado y la tabla de detalle no tiene esa columna.
   - Conservarlo permitiría auditar la actividad validada; si no es parte del dominio, conviene retirarlo del contrato para no comunicar una relación inexistente.

2. Agregar filtros y paginación al listado por año, semana, usuario, cliente o SKU.

3. Evitar descargar todos los detalles en la consulta inicial.
   - Considerar un listado paginado de cabeceras y un endpoint de detalle bajo demanda.

4. Mejorar el resultado de la previsualización con resumen de filas leídas, omitidas, válidas y rechazadas.

5. Centralizar y hacer configurables los alias de clientes para no requerir cambios de código cuando cambie el catálogo.

6. Optimizar la resolución de clientes.
   - Actualmente cada previsualización carga todos los registros de `public.cliente` en memoria.
   - Conviene limitar la consulta a candidatos normalizados o mantener un catálogo indexado apropiadamente.

7. Incorporar límites por cantidad de filas y una estrategia de lectura por bloques para controlar el consumo de memoria de PhpSpreadsheet.

8. Responder errores de base por violaciones conocidas con códigos y mensajes funcionales, preservando el detalle técnico únicamente en los logs.

9. Normalizar nombres de propiedades entre Excel, API, TypeScript y base de datos (`Total Semana`, `value`, `weekly_total`; `producto`, `product`).

10. Definir operaciones explícitas para eliminar, restaurar o consultar versiones históricas si forman parte del uso esperado.

11. Mostrar en la interfaz la fecha del último reemplazo real y el usuario autenticado que realizó la operación.

## Deuda técnica

1. Agregar al repositorio las migraciones de creación de `clients.scheduled_detail` y `clients.weekly_scheduled_detail`.
   - La estructura actual depende de tablas creadas fuera del historial disponible.
   - Una instalación desde cero no puede reproducir el submódulo usando solo `php artisan migrate`.

2. Crear pruebas automatizadas de backend para:
   - semanas ISO de 52 y 53 semanas;
   - archivo, hoja y encabezados inválidos;
   - filas vacías, cero, `-`, enteros negativos y valores no enteros;
   - separación de SKU y producto;
   - resolución exacta, por alias, parcial, ambigua, inactiva y eliminada de clientes;
   - resolución exacta, alternativa, inactiva y eliminada de actividades;
   - errores asociados al número correcto de fila;
   - creación, conflicto `409`, confirmación y rollback de reemplazo;
   - duplicados dentro del payload y solicitudes concurrentes;
   - intentos de guardar sin previsualización;
   - soft deletes de detalles y listado de relaciones;
   - autenticación, autorización y aislamiento de información por usuario o rol.

3. Crear pruebas de frontend para selección de semana, errores de archivo, previsualización, confirmación de reemplazo, filtros y modal de detalle.

4. Extraer `FormRequest` para previsualización y guardado, y centralizar las reglas compartidas de año, semana, usuario y detalle.

5. Separar del controlador los servicios de lectura de Excel, resolución de catálogos y persistencia semanal.

6. Usar nombres convencionales de relaciones Eloquent (`weeklyScheduledDetails`, `scheduledDetail`) o documentar formalmente la excepción en snake case.

7. Declarar casts de fecha y tipos numéricos en los modelos para estabilizar los contratos serializados.

8. Calificar explícitamente las tablas del esquema `clients` en los modelos o configurar la conexión de forma que no dependa implícitamente del `search_path`.

9. Respaldar la unicidad de los detalles con una restricción compatible con `SoftDeletes`, si la clave funcional se confirma.

10. Evaluar índices para `weekly_scheduled_detail.scheduled_detail_id`, `weekly_scheduled_detail.client_id` y los campos de catálogo usados en la resolución y el listado.

11. Eliminar la duplicación de cálculo de semanas ISO entre frontend y backend mediante una fuente de reglas claramente probada.

12. Manejar respuestas no JSON en el composable frontend para evitar que `response.json()` oculte el error HTTP original.

## Validaciones

1. Definir y aplicar el rango válido de `weekly_total` tanto en previsualización como en guardado.

2. Aclarar si el valor cero debe omitirse siempre o si debe poder persistirse, dado que la base permite `0` pero la importación lo descarta.

3. Derivar `username` de la sesión backend y limitar su longitud y formato según la identidad real del sistema.

4. Validar que el cliente exista, esté activo, no esté eliminado y sea accesible para el usuario que realiza la carga.

5. Validar que la actividad esté activa, no esté eliminada y coincida inequívocamente con cliente, SKU y producto.

6. Normalizar SKU y producto antes de comparar duplicados, incluyendo espacios, mayúsculas y representación numérica proveniente de Excel.

7. Definir longitudes máximas en la previsualización coherentes con las reglas de `store` y con las columnas PostgreSQL.

8. Rechazar previsualizaciones sin filas importables con un mensaje específico.

9. Validar una cantidad máxima de hojas, filas y celdas antes de convertir la hoja completa en memoria.

10. Garantizar que la misma información previsualizada sea la que se guarda.
    - Evaluar un token de previsualización, almacenamiento temporal o revalidación completa del payload.

11. Verificar durante despliegues que el índice único parcial exista realmente, además de comprobar el registro de la migración.

12. Validar de forma explícita el contrato de `activity_id`: tipo string, obligatoriedad y destino si finalmente se persiste.

13. Validar la autorización del listado y definir si debe devolver todas las programaciones o solo las visibles para el usuario autenticado.

## Dudas funcionales

1. ¿La unicidad correcta es por usuario, año y semana, o debe existir una única programación semanal global para todos los usuarios?

2. ¿El reemplazo debe sustituir toda la semana o combinar las filas nuevas con las existentes?

3. ¿Un total semanal igual a cero representa ausencia de programación, una programación explícita en cero o una fila que siempre debe ignorarse?

4. ¿`activity_id` debe almacenarse para enlazar cada detalle con la actividad validada?

5. ¿Qué debe ocurrir cuando varias actividades comparten cliente y SKU, pero ninguna coincide exactamente con el producto?

6. ¿Los alias de centros actuales son reglas permanentes del negocio o una solución temporal para una plantilla específica?

7. ¿La comparación de nombres de cliente y producto debe ignorar tildes, puntuación, mayúsculas y sufijos comerciales?

8. ¿La combinación única de un detalle es cliente + SKU + producto, cliente + actividad, o alguna otra clave?

9. ¿Los detalles eliminados durante un reemplazo deben ser consultables como versiones históricas?

10. ¿Qué roles pueden cargar, reemplazar y consultar, y debe existir un permiso distinto para cada operación?

11. ¿Las programaciones son privadas por usuario, compartidas por área o visibles para toda la organización?

12. ¿Los campos `notes` deben formar parte de la cabecera, de cada detalle o permanecer sin uso?

13. ¿El año y la semana seleccionados manualmente son la única fuente del periodo o la plantilla también debe declarar un periodo verificable?

14. ¿El producto del Excel debe conservarse como texto histórico o mostrarse siempre desde el nombre vigente de `public.actividad`?

