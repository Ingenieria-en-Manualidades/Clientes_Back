# Pendientes - Unidades diarias

## Bugs

1. Corregir la ruta masiva diaria inexistente.
   - `POST /api/createUnidadesDiariasMasivo` apunta a `UnidadesDiariasController::createBulk`.
   - El controlador actual no define ese método.

2. Hacer atómica la actualización versionada de una meta.
   - Se crea la nueva versión, se elimina lógicamente la anterior y después se reasignan las unidades sin transacción.
   - Una falla intermedia deja datos parcialmente actualizados.

3. Corregir la actualización de metas sin unidades diarias.
   - Si la reasignación afecta cero filas, el controlador responde HTTP `409` después de haber creado la nueva versión y eliminado la anterior.
   - Una meta sin distribución diaria debería poder actualizarse o rechazarse antes de modificar datos.

4. Evitar duplicados por concurrencia.
   - La comprobación y la inserción están separadas tanto para metas como para unidades diarias.
   - No existen restricciones únicas funcionales en la base.

5. Corregir el cálculo `total_valor_meta` de la integración.
   - `SUM(mu.valor) OVER ()` suma el valor mensual una vez por cada fila diaria resultante.
   - Una meta con varias unidades del día o varias filas puede inflar el total.

6. Corregir `metaUnidadesExists` para considerar el área.
   - El endpoint verifica únicamente cliente y mes.
   - Puede responder que existen unidades aunque la consulta corresponda a otra área válida del mismo cliente.

7. Evitar referencias a áreas inválidas o cruzadas entre clientes.
   - `area_id_groot` no tiene clave foránea y los controladores no comprueban que el área pertenezca al cliente.
   - La integración une únicamente por `area_id`.

8. Corregir respuestas y nombres inconsistentes.
   - `getUnidadesDiariaID` devuelve el dato bajo la clave `meta_unidades`.
   - Algunos mensajes usan singular/plural incorrecto o no asignan un código HTTP de error.

9. Corregir el manejo del estado `activo`.
   - Las versiones sustituidas se marcan `n`, pero la mayoría de consultas solo filtra `deleted_at`.
   - Las unidades diarias conservan `activo = s` incluso cuando se eliminan durante un reemplazo masivo.

10. Evitar resultados ambiguos cuando `cliente_endpoint_id` no sea único.
    - Los controladores usan el primer cliente encontrado.
    - La base revisada no muestra una restricción única para ese identificador.

## Mejoras

1. Proteger todas las rutas administrativas con autenticación y autorización backend; conservar un mecanismo separado y explícito para la integración externa.

2. Configurar la URL de IMEC+/Groot mediante entorno y eliminar la URL fija de desarrollo en `getAreasImec`.

3. Habilitar verificación TLS en el cliente HTTP de áreas.

4. Consolidar la fuente de áreas.
   - El frontend consulta `apiGroot` directamente.
   - El backend ofrece `getAreasImec` y la integración usa `public.area`.
   - Definir una fuente canónica y comportamiento ante diferencias.

5. Optimizar consultas e índices.
   - Evaluar índices para `meta_unidades(clientes_id, area_id_groot, fecha_meta)`, `unidades_diarias(meta_unidades_id, fecha_programacion)` y `clientes.cliente_endpoint_id`.
   - Sustituir búsquedas mensuales con `LIKE` por rangos de fechas.

6. Evitar el patrón N+1 o recorridos innecesarios en listados y resolver nombres de área en el backend desde una fuente validada.

7. Unificar contratos, nombres de campos, códigos HTTP y formato de errores.

8. Incorporar paginación y filtros de periodo/área en listados cuando aumente el volumen.

9. Derivar `usuario` de la identidad autenticada en vez de confiar en texto enviado por el cliente.

## Deuda técnica

1. Crear pruebas automatizadas para:
   - creación mensual individual y masiva;
   - fechas diarias fuera del mes;
   - reemplazo masivo y rollback;
   - versionado con y sin unidades relacionadas;
   - duplicados y concurrencia;
   - selección de la versión más reciente;
   - listados a través de versiones;
   - actualización diaria;
   - soft deletes y estado `activo`;
   - aislamiento entre clientes y áreas;
   - token de integración y cálculo de totales;
   - fallas y respuestas del servicio de áreas.

2. Extraer clases `FormRequest` para cada operación y centralizar reglas compartidas.

3. Separar servicios de metas mensuales, versionado, distribución diaria, áreas e integración externa.

4. Sustituir consultas basadas en `first()` cuando la cardinalidad funcional debe ser única por comprobaciones explícitas y garantías de base.

5. Eliminar imports y código comentado sin uso en ambos controladores.

6. Agregar `motivo_actualizacion` a `$fillable` de `MetaUnidades` si se adopta asignación masiva y decidir si `actualizaciones` debe mantenerse en `UnidadesDiarias`.

7. Alinear modelos y migraciones explícitamente con el esquema PostgreSQL `clients` para no depender únicamente del `search_path`.

8. Definir tipos coherentes para identificadores; varios endpoints validan IDs de base como `string` mientras las columnas son `bigint`.

## Validaciones

1. Definir y aplicar en backend si `valor` mensual y diario debe ser mayor o igual a cero en todas las operaciones, no solo en cargas masivas.

2. Validar que `fecha_meta` siga una convención mensual canónica, por ejemplo el primer día del mes.

3. Validar siempre que `fecha_programacion` pertenezca al mes de la meta, incluida la creación diaria individual.

4. Validar que cliente y área existan, estén activos, no estén eliminados y pertenezcan entre sí.

5. Validar estructura interna de `arraysAreas`, incluidos `area_id` y `nombre_area`, antes de mapear el listado.

6. Validar propiedad del recurso en `getMetaUnidades`, `updateMetaUnidades`, listados y operaciones de unidades diarias.

7. Respaldar la unicidad mensual y diaria con restricciones o índices compatibles con `SoftDeletes`.

8. Validar longitud y contenido de `usuario` y `motivo_actualizacion`.

9. Validar formato de `date` y tipo de `client_id` en `getDailyUnitsOfDay` antes de consultar.

10. Validar configuración no vacía y rotación segura de la clave usada por la integración.

11. Filtrar áreas inactivas o eliminadas en la integración.

12. Evitar exponer mensajes internos de excepciones en respuestas.

## Dudas funcionales

1. ¿Debe existir exactamente una meta activa por cliente, área y mes?

2. ¿Debe existir exactamente una unidad activa por cliente, área y fecha?

3. ¿El valor mensual debe ser igual a la suma de las unidades diarias o solo actúa como límite/referencia?

4. ¿Se permiten valores mensuales o diarios iguales a `0`?

5. ¿Qué debe ocurrir con días no incluidos en una carga masiva: valor cero, ausencia de registro o conservación del valor anterior?

6. ¿Actualizar una meta sin unidades relacionadas debe ser una operación válida?

7. ¿El reemplazo masivo debe conservar historial consultable de las unidades diarias anteriores?

8. ¿`actualizaciones` representa versión de meta, cantidad de cambios o ambos conceptos?

9. ¿El campo `activo` debe tener efecto independiente de `deleted_at`?

10. ¿Cuál es la fuente oficial de áreas: servicio IMEC+/Groot, tabla `public.area` o catálogo enviado por el frontend?

11. ¿El total expuesto a Groot debe sumar metas únicas por área, unidades diarias del día o ambas métricas por separado?

12. ¿Qué roles pueden crear, reemplazar y versionar metas, y cuáles solo pueden registrar unidades diarias?
