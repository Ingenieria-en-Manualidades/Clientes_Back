# BITACORA.md

Registro de cambios del modulo `usuarios`.

Cada cambio funcional, tecnico o documental debe registrarse aqui con fecha, motivo, archivos afectados y notas relevantes.

## 2026-05-20

### Replica de estructura documental backend

Motivo:
- Replicar en backend la estructura documental usada en frontend para mantener trazabilidad por modulo.

Archivos afectados:
- `docs/modules/usuarios/CONTEXT.md`
- `docs/modules/usuarios/BITACORA.md`
- `docs/modules/usuarios/PENDING.md`

Cambios realizados:
- Se creo la carpeta del modulo en backend con los tres archivos base de documentacion.
- No se realizaron cambios funcionales en codigo del modulo.

## 2026-05-20

### Preservar contrasena al actualizar usuarios

Motivo:
- Evitar que un valor marcador enviado por el frontend sea interpretado como una contrasena nueva al actualizar datos, rol, clientes o permisos.

Archivos afectados:
- `app/Http/Controllers/Admon/UserController.php`

Cambios realizados:
- `updateFrontend` mantiene la contrasena actual cuando el campo viene vacio, `null` o con el marcador heredado `*`.

## 2026-05-26

### Lectura de permisos directos en login

Motivo:
- Los permisos asignados desde la vista de Usuarios se guardan en la tabla `user_permission`.
- El login solo estaba devolviendo permisos obtenidos por roles de Spatie, por lo que permisos directos como `view_clients` no llegaban al frontend.

Archivos afectados:
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/PermissionController.php`

Cambios realizados:
- `AuthController@login` ahora combina permisos por rol con permisos directos de `user_permission`.
- Se eliminan duplicados antes de devolver la lista `permissions` al frontend.
- `PermissionController@getListPermissions` asegura la existencia de `view_clients` para poder asignarlo desde la vista de usuarios.
- Se verifico que `DEVUSER` tuviera `view_administration`, `view_users` y `view_clients`, tanto directo como por rol `Dev`.

## 2026-05-29

### Mensaje claro para credenciales invalidas

Motivo:
- Evitar que el usuario vea un error tecnico `422` cuando ingresa usuario o contrasena incorrectos.

Archivos afectados:
- `app/Http/Controllers/AuthController.php`

Cambios realizados:
- `AuthController@login` ahora responde `401` para credenciales incorrectas.
- La respuesta incluye `title = Credenciales incorrectas` y `message = Usuario o contraseña incorrectos.`.
- Se mantiene el incremento de intentos fallidos y el log de intento invalido.

Verificacion:
- `php -l app\Http\Controllers\AuthController.php`
- Resultado: sin errores de sintaxis.
