# Bitácora - Programación detallada

Registro de cambios relevantes del submódulo `Programación detallada`. Las entradas iniciales fueron reconstruidas a partir de los archivos disponibles en el workspace, debido a que esta copia no conserva historial Git propio del submódulo.

## 2026-08-01

### Protección de la cabecera semanal activa

Qué se hizo:

- Se agregó `database/migrations/2026_08_01_000000_add_active_schedule_user_week_unique_index.php`.
- La migración define el índice único parcial `scheduled_detail_active_user_week_unique` para `username`, `year` y `week_number` cuando `deleted_at IS NULL`.

Por qué se hizo:

- El flujo considera que un usuario solo puede tener una programación activa para una combinación de año y semana.
- La validación de aplicación por sí sola no protege ante solicitudes concurrentes.

Impacto del cambio:

- En una base donde el índice esté materializado, PostgreSQL impide crear dos cabeceras activas con la misma clave funcional.
- Las programaciones eliminadas lógicamente quedan fuera del índice y no bloquean una nueva cabecera.

## 2026-08-04

### Interfaz frontend de carga y consulta

Qué se hizo:

- Se agregó la página de carga `pages/objetivos/programacion-detallada.vue`.
- Se agregó la página de consulta `pages/objetivos/programacion-detallada-table.vue`.
- Se incorporaron el composable de API, los tipos TypeScript, la configuración de pestañas y columnas, y la entrada del menú con el permiso `view_scheduled_detail`.
- La carga permite seleccionar año y semana ISO, previsualizar el Excel, buscar filas, guardar y confirmar el reemplazo de una programación existente.
- La consulta muestra las cabeceras y abre los detalles semanales en un modal.

Por qué se hizo:

- Se necesitaba un flujo visible para revisar la información del archivo antes de persistirla y otro para consultar lo ya guardado.

Impacto del cambio:

- Los usuarios autorizados desde el menú pueden operar el submódulo desde el frontend.
- La confirmación evita reemplazos accidentales desde el flujo normal de la interfaz.
- Los contratos TypeScript centralizan la forma de las solicitudes y respuestas consumidas por ambas páginas.

## 2026-08-05

### API y modelos de programación detallada

Qué se hizo:

- Se agregaron `ScheduledDetailController`, `ScheduledDetail` y `WeeklyScheduledDetail`.
- Se registraron en `routes/api.php` los endpoints de previsualización, guardado y consulta.
- La previsualización incorporó lectura de la hoja `Detalle Semana`, búsqueda flexible de encabezados, separación de SKU/producto, resolución de clientes y validación contra actividades.
- El guardado incorporó transacción, detección de una programación existente, confirmación de reemplazo y creación masiva de detalles.
- El listado incorporó la relación cabecera-detalles y el nombre de cliente obtenido desde `public.cliente`.

Por qué se hizo:

- El frontend requería una capa backend que validara la plantilla contra los catálogos operativos y persistiera la programación semanal de forma atómica.

Impacto del cambio:

- La información del archivo puede validarse sin escritura previa.
- Una falla durante el guardado o reemplazo revierte el conjunto de cambios de esa solicitud.
- Las programaciones y sus detalles pueden consultarse mediante un contrato JSON estable.

## 2026-08-11

### Ajustes integrados al flujo de validación y reemplazo

Qué se hizo:

- El estado disponible al cierre de los archivos incluye validación de semanas ISO reales, alias de centros, reporte de errores por fila y omisión de filas con total cero o `-`.
- El flujo frontend incluye la segunda solicitud con `replace_existing = true` después de la confirmación del usuario.
- El listado presenta filtros locales y el detalle semanal asociado a cada cabecera.

Por qué se hizo:

- Era necesario tolerar variaciones conocidas de la plantilla y evitar que una actualización sustituyera datos sin confirmación explícita.

Impacto del cambio:

- Los errores de la plantilla se identifican antes del guardado.
- Los reemplazos conservan la cabecera y sustituyen de forma transaccional el conjunto activo de detalles.
- La consulta permite revisar la composición de cada programación sin una llamada adicional por cabecera.

## 2026-08-19

### Documentación técnica inicial

Qué se hizo:

- Se creó la carpeta `docs/programacion detallada`.
- Se agregaron `CONTEXT.md`, `BITACORA.md` y `PENDING.md`.
- Se documentaron el flujo backend y frontend, los contratos, las tablas verificadas en PostgreSQL, las reglas actuales, los riesgos y los asuntos pendientes.

Por qué se hizo:

- Se necesitaba una fuente de contexto separada y mantenible para futuras intervenciones humanas o asistidas por IA.

Impacto del cambio:

- El funcionamiento vigente queda centralizado en `CONTEXT.md`.
- Los cambios relevantes quedan separados de los trabajos futuros.
- Los bugs, mejoras, validaciones y dudas funcionales quedan centralizados en `PENDING.md`.

