# Pendientes - Clientes

Pendientes centralizados del modulo backend `Clientes`.

## Sincronizacion

1. Revisar reglas finas de sincronizacion desde `public.cliente`.
   - Confirmar si se deben sincronizar clientes inactivos.
   - Confirmar si eliminados en `public.cliente` deben desactivar o eliminar en destinos.
   - Confirmar si la llave correcta siempre es `cliente_id`.
   - Confirmar si `hora_extra` debe mapear directo a `surveys.clients.overtime` con formato `HH:MM:SS`.

2. Definir comportamiento ante duplicados.
   - Mismo nombre con distinto endpoint.
   - Mismo endpoint con distinto nombre.
   - Clientes desactivados o eliminados.

## Relaciones

1. Revisar `ClienteUserController@getClientsByUserId`.
   - Pendiente: proteger ruta con `auth:sanctum` si aplica.

2. Definir si el backend debe crear clientes en `surveys.clients`.
   - Hoy se edita `surveys.clients`, pero no se crean nuevos registros desde el submodulo.
   - Confirmar campos obligatorios y valores por defecto.
   - Pendiente: definir si en bases locales sin tabla de clientes en `surveys` se debe crear esa estructura o mantenerla como integracion opcional.

## Validacion

1. Agregar pruebas para `PUT api/updateSurveyClient/{id}`.
2. Validar impactos de cambiar `cliente_endpoint_id` en objetivos, login y seleccion de clientes.
3. Separar permisos especificos para crear, editar y sincronizar clientes.
   - `view_clients` ya controla la visibilidad del submodulo.
   - Falta definir permisos de accion si se requiere controlar operaciones internas.

## Testing

1. Corregir migraciones generales de testing para poder ejecutar `artisan test` completo.
   - La suite completa falla porque la migracion de `accidentes` referencia `tablero_sae` antes de que la relacion esperada exista.
   - La suite especifica de `Clientes` pasa correctamente.
