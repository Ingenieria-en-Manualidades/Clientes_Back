# Pendientes - Metas

## Bugs

1. Hacer atómica la creación de `meta` y `tablero_sae`.
   - Actualmente se ejecutan mediante dos peticiones independientes.
   - Si falla `guardarTablero`, la meta queda huérfana, sin periodo ni cliente.
   - Definir una sola operación transaccional o un mecanismo seguro de compensación/reintento.

2. Evitar metas duplicadas por concurrencia.
   - La consulta de existencia y la inserción no están protegidas por una restricción única.
   - Dos solicitudes simultáneas pueden superar la comprobación por cliente y mes.

3. Corregir las relaciones Eloquent de cliente y Tablero SAE.
   - `Cliente::tablero_sae()` usa `id` como clave foránea en lugar de `cliente_id`.
   - `Tablero_Sae::clientes()` relaciona `id` con `id` en lugar de `cliente_id` con `clientes.id`.

4. Corregir mensajes con caracteres mal codificados.
   - Hay respuestas JSON y comentarios con texto mojibake, por ejemplo en los mensajes de éxito y validación.

5. Evitar resultados ambiguos cuando `cliente_endpoint_id` esté repetido.
   - No existe una restricción única en la base para ese campo.
   - La creación obtiene todos los clientes coincidentes y utiliza el primero.

6. Validar el orden del rango al listar.
   - `fecha_inicio` y `fecha_fin` se validan de forma independiente, pero no se rechaza un inicio posterior al fin.

## Mejoras

1. Proteger `guardarMeta`, `listarMetas` y `guardarTablero` con autenticación y autorización backend acordes al permiso funcional de metas.

2. Unificar el contrato de respuestas JSON.
   - Normalizar `success`, `message`, `data`, códigos HTTP y formato de errores.
   - Evitar devolver el objeto completo de la solicitud como `data` o `errors`.

3. Incorporar una operación explícita para actualizar metas si el negocio permite modificaciones posteriores.

4. Optimizar las consultas por cliente, fecha y relaciones.
   - Evaluar índices para `tablero_sae.fecha`, `tablero_sae.cliente_id`, `tablero_sae.meta_id`, `calidad.meta_id` y `clientes.cliente_endpoint_id`.
   - Reemplazar la comparación mensual con `LIKE` sobre `timestamp` por límites de fecha.

5. Definir una respuesta idempotente o recuperable para reintentos del frontend cuando la conexión se interrumpa después de crear la meta.

## Deuda técnica

1. Crear pruebas automatizadas para:
   - creación correcta de meta y asociación de Tablero SAE;
   - cliente inexistente;
   - duplicado por cliente y mes;
   - concurrencia;
   - falla y rollback de cualquiera de las dos inserciones;
   - filtros por rango y valores predeterminados;
   - exclusión de registros eliminados lógicamente;
   - autorización de rutas.

2. Extraer clases `FormRequest` para los contratos de creación y listado.

3. Sustituir consultas con `get()` y acceso al índice `[0]` por operaciones que expresen la cardinalidad esperada, como `firstOrFail()` o equivalentes controlados.

4. Eliminar imports y código comentado que no se utilizan en `MetaController`.

5. Revisar y tipar de extremo a extremo los payloads y respuestas de `guardarMeta` y `guardarTablero`.

6. Alinear modelos y migraciones con el esquema PostgreSQL `clients` de forma explícita para evitar depender implícitamente del `search_path`.

7. Definir la estrategia de borrado para metas relacionadas con `tablero_sae` y `calidad`, incluidas reglas de claves foráneas y restauración de registros con `SoftDeletes`.

## Validaciones

1. Aplicar en backend el rango permitido para los cinco indicadores; el frontend muestra y valida actualmente valores entre `0` y `100`, pero la API acepta cualquier entero.

2. Confirmar y validar si los indicadores deben aceptar únicamente enteros o también decimales.

3. Cambiar `fecha` de `string` a una regla de fecha y formato mensual explícito en `guardarMeta` y `guardarTablero`.

4. Validar que `fecha_inicio` sea anterior o igual a `fecha_fin`.

5. Validar de forma inequívoca la existencia y el estado habilitado del cliente; actualmente se excluye `deleted_at`, pero no se comprueba el campo `activo`.

6. Validar en backend la unicidad funcional de cliente y mes y respaldarla con una garantía de base de datos compatible con eliminación lógica.

7. Validar que el `meta_id`, `cliente_id` y `fecha` enviados a `guardarTablero` correspondan a la misma operación autorizada, en lugar de aceptar identificadores arbitrarios existentes.

8. Evitar exponer mensajes internos de excepciones en las respuestas HTTP `500`.

## Dudas funcionales

1. ¿La regla definitiva es exactamente una meta activa por cliente y mes?

2. ¿Los cinco valores representan porcentajes enteros entre `0` y `100`, incluidos los indicadores de desperdicio?

3. ¿Se permiten metas para meses pasados, el mes actual y meses futuros, o debe restringirse el periodo?

4. ¿Una meta existente puede editarse, versionarse o únicamente eliminarse y crearse de nuevo?

5. ¿Qué permiso debe autorizar la creación y cuál debe autorizar la consulta en el backend?

6. ¿`cliente_endpoint_id` debe ser único globalmente entre clientes activos?

7. ¿Qué debe ocurrir con `tablero_sae` y `calidad` al eliminar o restaurar una meta?

8. ¿La fecha debe almacenarse como el primer día del mes o existe otra convención canónica para representar el periodo mensual?
