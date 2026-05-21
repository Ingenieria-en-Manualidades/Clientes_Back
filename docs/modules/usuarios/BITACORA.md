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
