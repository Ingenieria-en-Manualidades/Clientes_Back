# Contexto - Usuarios

## Objetivo
Administrar cuentas de empleados y contactos, asociaciones con clientes, roles, permisos, estado y credenciales.

## Alcance
- Crear, listar, actualizar, habilitar, deshabilitar y restablecer usuarios; autenticar y recuperar contraseñas.

## Usuarios
- Administradores, empleados vinculados a `public.empleado` y contactos de `surveys.customer_contact`.

## Tablas y campos clave
- `users`: `id`, `name`, `email`, `password`, `activo`, `empleado_id`, `reset_password`, `deleted_at`.
- `cliente_user`, `clientes`, tablas Spatie, `user_permission`, `customer_contact`, `tokens_passwords` y `personal_access_tokens`.

## Archivos relevantes
- `app/Http/Controllers/Admon/UserController.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/PermissionController.php`
- `app/Models/User.php`
- `routes/api.php`

## Reglas de negocio
- El nombre se normaliza a mayúsculas; un empleado no puede tener dos usuarios.
- Cliente `0` asocia todos los clientes.
- Clientes, rol y permisos se sincronizan en transacción.
- Contraseña vacía, nula o `*` conserva la existente; el estado combina bandera y soft delete.

## Validaciones
- Tipo, datos personales, clientes y rol son obligatorios; la contraseña de creación exige ocho caracteres y confirmación.

## Dependencias
- Sanctum, Jetstream/Fortify, Spatie Permission, clientes, encuesta y esquemas externos.

## Riesgos
- Protección desigual de rutas y dependencia migratoria de `public.empleado`.

## Consideraciones
- El login combina permisos de rol y directos sin duplicados.
