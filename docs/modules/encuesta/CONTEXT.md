# Contexto - Encuesta

## Objetivo
Registrar encuestas de satisfacción, consultar respuestas anuales y soportar campañas de recordatorio.

## Alcance
- Consultar catálogos y contactos; guardar encuesta, trazabilidad, preguntas y respuestas en una transacción; enviar agradecimientos.

## Usuarios
- Contactos asociados a usuarios activos y operadores de campañas.

## Tablas y campos clave
- `surveys.survey`, `surveys.customer_contact`, `surveys.customer_contact_has_survey`, `surveys.survey_has_question` y tablas de respuestas simples, radio y booleanas.
- Claves: `survey_id`, `user_id`, `clients_id`, `question_id`, `version`, `year`.

## Archivos relevantes
- `app/Http/Controllers/SurveyController.php`
- `app/Models/survey/`
- `app/Services/CampaignService.php`
- `routes/api.php` y `routes/console.php`

## Reglas de negocio
- El usuario se normaliza a mayúsculas, debe estar activo y tener contacto.
- Cada envío crea trazabilidad versionada por contacto y año.
- El fallo del correo no revierte la encuesta.

## Validaciones
- Fecha, nombre, cargo, cliente, usuario y respuestas son obligatorios; falta validación anidada completa.

## Dependencias
- Esquema `surveys`, usuarios, clientes, correo, colas y configuración de campañas.

## Riesgos
- Rutas sin `auth:sanctum`, catálogos no validados y joins dependientes del `search_path`.

## Consideraciones
- Los tipos de respuesta admitidos son `simple_answer`, `input_radio_answer` y `boolean_answer`.
