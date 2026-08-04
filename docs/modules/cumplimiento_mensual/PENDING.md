# Pendientes - Cumplimiento mensual

## Bugs

1. Corregir el registro de una calificación con valor `0` cuando ya existe la otra calificación.
   - `CalidadController::create` decide mediante `if ($checklist)` qué campo actualizar.
   - Un checklist igual a `0` se interpreta como ausente y puede intentar actualizar `inspeccion` con `null` o devolver un conflicto incorrecto.

2. Hacer consistente y atómico el guardado de calificación y evidencia.
   - El frontend guarda primero `calidad` y después llama a `guardarArchivo`.
   - Una falla en el segundo paso deja la calificación sin evidencia.
   - El archivo ZIP se escribe antes de insertar `files`; una falla de base de datos puede dejar un archivo físico huérfano.

3. Evitar pérdida de evidencia durante el reemplazo.
   - El flujo actual elimina lógica y físicamente la evidencia anterior antes de cargar la nueva.
   - Si la nueva carga falla, no existe rollback ni restauración automática.

4. Corregir el manejo de errores de validación en `FileController`.
   - Se captura `ValidationException` sin importar `Illuminate\Validation\ValidationException`.
   - Las excepciones de validación pueden caer en el bloque general y responder HTTP `500` en lugar de `422`.

5. Corregir la asociación entre evidencia, cliente y tablero.
   - `guardarArchivo` comprueba que el cliente exista, pero no que `tablero_sae_id` pertenezca a ese cliente.
   - `deleteFile` recibe `id` y `url` por separado sin comprobar que correspondan al mismo registro.

6. Evitar duplicados de calidad bajo concurrencia.
   - La búsqueda y creación están separadas.
   - `clients.calidad` no tiene una restricción única sobre `meta_id`.

7. Corregir el año usado al guardar una evidencia nueva.
   - Los formularios envían el año actual mediante `new Date().getFullYear()` y no el año del mes seleccionado.
   - Una carga para otro año puede almacenarse en un directorio incorrecto.

8. Corregir el estado de errores en el formulario de checklist.
   - A diferencia del formulario de inspección, no reinicia todas las banderas de error al comenzar un nuevo envío.
   - Un error anterior puede impedir un envío posterior aunque el usuario haya corregido los campos.

9. Evitar colisiones y sobrescrituras de archivos.
   - El nombre del ZIP usa fecha de carga y nombre original dentro del mismo directorio.
   - Dos cargas con esos mismos valores producen la misma ruta y pueden sobrescribirse mientras quedan varios registros en `files`.

10. Corregir códigos y mensajes HTTP inconsistentes.
    - `listarArchivos` responde `405` cuando el cliente no tiene metas, aunque no es un error de método HTTP.
    - Algunos mensajes mencionan objetivos o descargas en operaciones distintas.

## Mejoras

1. Proteger todos los endpoints de calidad y evidencias con autenticación y autorización backend acordes a `view_objetivos_calidad` y a permisos separados de escritura, reemplazo y descarga.

2. Crear una operación transaccional de aplicación para registrar calificación y evidencia como una sola unidad recuperable.

3. Implementar un reemplazo seguro de evidencia.
   - Validar y guardar primero el nuevo archivo.
   - Cambiar la referencia de manera atómica.
   - Eliminar el archivo anterior solo después del éxito.

4. Mejorar el listado de evidencias con filtros por cliente, periodo y tipo sin recorrer tablero por tablero ni ejecutar una consulta de archivos por cada registro.

5. Usar un tipo de evidencia explícito y validado en la base en vez de deducirlo buscando `checklist` en la ruta.

6. Optimizar consultas y relaciones.
   - Evaluar índices para `calidad.meta_id`, `files.tablero_sae_id`, `tablero_sae.meta_id`, `tablero_sae.cliente_id`, `tablero_sae.fecha` y `clientes.cliente_endpoint_id`.
   - Sustituir `LIKE` mensual sobre `timestamp` por límites de fecha.

7. Unificar contratos y códigos de las respuestas JSON de calidad y archivos.

8. Incorporar una estrategia de reconciliación para detectar y reparar archivos físicos sin registro y registros cuyo archivo ya no existe.

## Deuda técnica

1. Crear pruebas automatizadas para:
   - creación inicial de checklist e inspección;
   - completar el segundo valor, incluyendo `0`;
   - rechazo de valores repetidos;
   - meta o cliente inexistentes;
   - duplicados concurrentes;
   - rangos de consulta y eliminación lógica;
   - carga, descarga, listado y eliminación de evidencias;
   - ZIP vacío, corrupto o sin PDF;
   - fallas entre sistema de archivos y base de datos;
   - reemplazo y rollback;
   - autorización y aislamiento entre clientes.

2. Extraer clases `FormRequest` para cada endpoint y centralizar sus reglas.

3. Separar en servicios las responsabilidades de resultados mensuales, resolución de periodo, almacenamiento ZIP y persistencia de metadatos.

4. Sustituir consultas repetidas con `get()` y acceso `[0]` por operaciones con cardinalidad explícita y relaciones Eloquent corregidas.

5. Eliminar imports, variables y bloques comentados sin uso en `CalidadController` y `FileController`.

6. Corregir las relaciones Eloquent de cliente y tablero.
   - `Cliente::tablero_sae()` debe usar `cliente_id` como clave foránea.
   - `Tablero_Sae::clientes()` debe relacionar `cliente_id` con `clientes.id`.

7. Alinear modelos y migraciones explícitamente con el esquema PostgreSQL `clients` para no depender solo del `search_path`.

8. Definir nombres de dominio consistentes para inspección (`inspeccion`, `inspeccion_sol`, `Inspección sol`) en API, almacenamiento y frontend.

9. Tipar las respuestas y eliminar accesos a variables inexistentes en bloques `catch` de los composables frontend.

## Validaciones

1. Exigir exactamente uno de `checklist` o `inspeccion` en `guardarCalidad`.

2. Aplicar en backend el rango permitido para las calificaciones; el frontend usa enteros entre `0` y `100`, pero la API acepta cualquier entero.

3. Definir si se permiten decimales y ajustar de forma coherente frontend, validación y columnas de base de datos.

4. Validar `fecha` con un formato mensual explícito en lugar de aceptar cualquier `string` procesable por `DateTime`.

5. Validar `tipo_formulario` mediante una lista cerrada y hacerlo obligatorio en `verificarCalidad`.

6. Validar que `fecha_inicio` sea anterior o igual a `fecha_fin`.

7. Validar cliente activo y no eliminado en creación, verificación, listado y gestión de archivos.

8. Validar que `tablero_sae_id` corresponda al cliente autenticado, a la meta del mes y al resultado que se está registrando.

9. Validar que el `id` y la `url` de eliminación pertenezcan al mismo registro activo y al cliente autorizado.

10. Validar `year_file` contra el año de `tablero_sae.fecha`; no confiar en un año libre enviado por el frontend.

11. Establecer un tamaño máximo para el PDF y validar que el primer contenido del ZIP descargado sea realmente un PDF.

12. Sanitizar y normalizar nombre de cliente, tipo, año y nombre original antes de construir rutas y nombres de archivo.

13. Impedir rutas arbitrarias en descarga y eliminación; resolver siempre la ubicación desde un `files_id` autorizado.

14. Respaldar con restricciones de base de datos las reglas de unicidad acordadas para calidad y evidencias activas.

15. No exponer mensajes internos de excepciones en respuestas HTTP `500`.

## Dudas funcionales

1. ¿Debe existir exactamente una fila activa de `calidad` por meta mensual?

2. ¿Checklist e inspección admiten el valor `0` y únicamente enteros entre `0` y `100`?

3. ¿Cada calificación exige exactamente una evidencia o se permiten varias evidencias por tipo y mes?

4. ¿Una evidencia puede cargarse sin modificar la calificación y una calificación puede existir temporalmente sin evidencia?

5. ¿El reemplazo debe conservar historial y permitir restaurar versiones anteriores?

6. ¿La meta `meta.calidad` se usa solo como referencia visual o debe compararse formalmente con checklist e inspección?

7. ¿Cómo se calcula un único cumplimiento mensual cuando existen dos resultados: checklist e inspección?

8. ¿Qué fecha debe definir el nombre y directorio de la evidencia: mes evaluado, fecha de carga o ambas?

9. ¿Qué tamaño máximo de PDF se permite y cuánto tiempo deben conservarse las evidencias?

10. ¿Qué roles pueden registrar, reemplazar, descargar y eliminar evidencias?

11. ¿Qué debe mostrarse cuando existe el registro en `files` pero falta el ZIP, o existe el ZIP sin registro en la base?
