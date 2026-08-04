# Pendientes - Cumplimiento diario

## Bugs

1. Permitir el valor válido `0` en las actualizaciones.
   - `ObjetivoController::update` usa condiciones booleanas para `planificada`, `modificada` y `plan_armado`.
   - El entero `0` se interpreta como ausencia y produce “Ningún cambio hecho”.

2. Evitar que una actualización parcial borre indicadores existentes.
   - Cuando `plan_armado` activa la rama de indicadores, el controlador asigna también `calidad`, `desperfecto_me` y `desperfecto_pp`.
   - Como esos campos son anulables, un payload incompleto puede sobrescribir valores con `null`.

3. Evitar objetivos diarios duplicados bajo concurrencia.
   - La comprobación de existencia y la inserción son operaciones separadas.
   - La base no tiene una restricción única que garantice un registro activo por cliente y fecha.

4. Corregir los filtros de la búsqueda usada para actualizar.
   - La consulta no excluye explícitamente objetivos, tableros o clientes eliminados lógicamente.
   - Si encuentra un objetivo eliminado, `Objetivo::findOrFail` puede terminar en una respuesta HTTP `500` en lugar de un resultado controlado.

5. Evitar seleccionar asociaciones ambiguas.
   - La creación utiliza el primer `tablero_sae` encontrado para cliente y mes.
   - La actualización utiliza el primer objetivo encontrado para cliente y fecha.
   - Sin unicidad, un dato duplicado puede provocar que se modifique o relacione el registro equivocado.

6. Corregir las relaciones Eloquent de cliente y Tablero SAE.
   - `Cliente::tablero_sae()` usa `id` como clave foránea en vez de `cliente_id`.
   - `Tablero_Sae::clientes()` relaciona `id` con `id` en vez de `cliente_id` con `clientes.id`.

7. Corregir la relación definida en el modelo `Accidente`.
   - `Accidente::accidentes()` declara una autorrelación `hasMany` con claves que representan el mismo accidente y no el vínculo funcional esperado.
   - `Objetivo` no expone la relación inversa hacia sus accidentes.

8. Corregir contratos y mensajes inconsistentes.
   - Creación y actualización llaman `cliente_id` al identificador externo, mientras el listado usa `cliente_endpoint_id`.
   - El rechazo de una fecha duplicada dice “día de hoy” aunque la validación usa la fecha enviada.
   - La ausencia de cambios responde HTTP `404`, aunque el recurso puede existir.

9. Corregir bloques `catch` del frontend que referencian variables inexistentes al manejar fallos de `createObjetivos` o `updateObjetivos`.

## Mejoras

1. Proteger los tres endpoints con autenticación y autorización backend acordes al permiso de cumplimiento diario y a permisos diferenciados de lectura y escritura.

2. Separar las operaciones de actualización.
   - Producción planificada.
   - Producción modificada.
   - Indicadores diarios.
   - Cada contrato debe aceptar únicamente los campos de su caso de uso.

3. Permitir identificar actualizaciones por `objetivos_id` autorizado o utilizar una clave funcional garantizada, en lugar de depender de la primera coincidencia por fecha.

4. Optimizar consultas.
   - Evaluar índices para `objetivos.fecha`, `objetivos.tablero_sae_id`, `tablero_sae.fecha`, `tablero_sae.cliente_id`, `tablero_sae.meta_id`, `clientes.cliente_endpoint_id` y `accidentes.objetivos_id`.
   - Reemplazar comparaciones `LIKE` sobre `timestamp` por rangos de fecha.

5. Unificar el formato de respuestas JSON, errores de validación y códigos HTTP.

6. Definir una operación de consulta individual que permita al frontend cargar el estado actual antes de modificarlo.

7. Reactivar o reemplazar los límites de fecha comentados en los formularios una vez acordada la regla funcional.

## Deuda técnica

1. Crear pruebas automatizadas para:
   - creación correcta con meta mensual existente;
   - cliente o meta inexistentes;
   - duplicado por cliente y día;
   - concurrencia;
   - actualización de cada grupo, incluidos valores `0`;
   - payloads parciales de indicadores;
   - rangos de listado y orden de fechas;
   - exclusión de eliminados lógicos;
   - aislamiento entre clientes;
   - autorización;
   - relaciones con tablero, meta y accidentes.

2. Extraer clases `FormRequest` distintas para crear, listar y cada clase de actualización.

3. Sustituir consultas con `get()` y acceso `[0]` por operaciones que expresen la cardinalidad requerida.

4. Eliminar imports y código comentado sin uso en `ObjetivoController`.

5. Evitar el objeto mutable compartido `objObjetivo` en el frontend y definir payloads tipados por operación.

6. Alinear modelos y migraciones explícitamente con el esquema PostgreSQL `clients` para no depender únicamente del `search_path`.

7. Agregar en `Objetivo` la relación `hasMany` hacia accidentes y corregir nombres de clase y método del modelo relacionado.

## Validaciones

1. Validar `fecha` con una regla de fecha y formato diario explícito en creación y actualización.

2. Aplicar en backend valores no negativos para `planificada` y `modificada`.

3. Aplicar en backend el rango permitido para `plan_armado`, `calidad`, `desperfecto_me` y `desperfecto_pp`; el frontend usa 0–100.

4. Confirmar y validar si las cantidades e indicadores deben ser enteros o pueden aceptar decimales.

5. Validar que `fecha_inicio` sea anterior o igual a `fecha_fin`.

6. Validar que el cliente exista, esté activo y no esté eliminado.

7. Validar que el tablero y la meta mensual estén activos antes de crear o actualizar el objetivo.

8. Respaldar en la base de datos la unicidad funcional acordada para cliente y día, con comportamiento definido frente a `SoftDeletes`.

9. Exigir el conjunto completo de indicadores cuando se actualicen en bloque o actualizar solo los campos presentes sin borrar los demás.

10. Validar si `modificada` puede ser menor, igual o mayor que `planificada`.

11. Validar el rango temporal autorizado para captura: días anteriores, día actual y fechas futuras.

12. Evitar exponer mensajes internos de excepciones en respuestas HTTP `500`.

13. Para accidentes relacionados, validar cantidad no negativa, tipo permitido y pertenencia de `objetivos_id` al cliente autorizado.

## Dudas funcionales

1. ¿Debe existir exactamente un registro activo por cliente y día?

2. ¿Qué rango de fechas puede registrar o modificar cada rol?

3. ¿La producción planificada puede modificarse después de creada y debe conservarse historial?

4. ¿La producción modificada puede ser menor, igual o mayor que la planificada?

5. ¿Los valores de producción representan unidades enteras y admiten `0`?

6. ¿Los cuatro indicadores son porcentajes enteros entre `0` y `100`, incluido `0`?

7. ¿Los indicadores pueden registrarse sin producción modificada o incluso sin producción planificada?

8. ¿Las actualizaciones deben reemplazar valores existentes o solo completar campos nulos?

9. ¿Cómo se compara formalmente cada indicador diario con las metas mensuales de `clients.meta`?

10. ¿Qué debe ocurrir con los accidentes cuando el objetivo diario se elimina o restaura?

11. ¿El identificador de cliente debe normalizarse como `cliente_endpoint_id` en todos los endpoints?
