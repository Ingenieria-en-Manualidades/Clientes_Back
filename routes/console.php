<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

use App\Mail\RebrandingMail;
use App\Mail\SurveyNudgeMail;

/**
 * Comando demo por defecto de Laravel
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Helper: trae el cliente por cliente_endpoint_id y, si existen tablas pivote,
 * asocia el primer usuario para obtener su email (opcional).
 */
function findContactByEndpoint(int $endpointId): ?object {
    return DB::table('clientes as c')
        ->join('cliente_user as cu', 'cu.cliente_id', '=', 'c.id')
        ->join('surveys.customer_contact as cc', 'cc.user_id', '=', 'cu.id')
        ->where('c.cliente_endpoint_id', $endpointId)
        ->whereNotNull('cc.email')               
        ->orderByDesc('cc.id')                   
        ->select([
            'c.id as client_id',
            'c.cliente_endpoint_id',
            'cc.fullname as contact_name',
            'cc.email    as contact_email',
            'cc.cellphone as contact_phone',
        ])
        ->first();
}


/**
 * PREVIEW: Día 0 (lanzamiento) a un destinatario de prueba
 *
 * Uso:
 *  php artisan rebranding:preview {cliente_endpoint_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 *  - cliente_endpoint_id : valor en `clientes.cliente_endpoint_id`
 *  - --test              : correo destino (OBLIGATORIO, evita envío real)
 *  - --op                : logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones (default: maquila)
 *  - --name              : fuerza el nombre mostrado (si no, usa `clientes.nombre`)
 */
Artisan::command('rebranding:preview {cliente_endpoint_id} {--test=} {--op=maquila} {--name=}', function () {
    $endpointId = (int) $this->argument('cliente_endpoint_id');
    $emailTo    = (string) $this->option('test');     // obligatorio (preview)
    $op         = (string) $this->option('op') ?: 'maquila';

    if (!$emailTo) { $this->error('--test es obligatorio.'); return self::FAILURE; }

    $contact = findContactByEndpoint($endpointId);
    if (!$contact) { $this->error("No hay contacto para endpoint {$endpointId}."); return self::FAILURE; }

    // nombre mostrado (permite override con --name)
    $name         = (string) ($this->option('name') ?: ($contact->contact_name ?? 'Cliente'));
    $surveyUrl    = config('rebranding.survey_url');
    $contactEmail = $contact->contact_email ?? null;
    $contactPhone = $contact->contact_phone ?? null;

    Mail::to($emailTo)->send(new RebrandingMail(
        name:          $name,
        operation:     $op,
        surveyUrl:     $surveyUrl,
        contactEmail:  $contactEmail,   // footer/contacto en la plantilla
        contactPhone:  $contactPhone    // si tu mailable lo usa
    ));

    $this->info("Preview enviado a {$emailTo} (endpoint={$endpointId}, op={$op}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW del Día 0 a un correo de prueba');


/**
 * PREVIEW: Recordatorios (waves) a un destinatario de prueba
 *
 * Uso:
 *  php artisan survey:nudge:preview {wave} {cliente_endpoint_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 *  - wave                : day3|day7|thanks|day14|nov|dec_mid
 *  - cliente_endpoint_id : valor en `clientes.cliente_endpoint_id`
 *  - --test              : correo destino (OBLIGATORIO)
 *  - --op                : logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones (default: maquila)
 *  - --name              : fuerza el nombre mostrado (si no, usa `clientes.nombre`)
 */
Artisan::command('survey:nudge:preview {wave} {cliente_endpoint_id} {--test=} {--op=maquila} {--name=}', function () {
    $wave    = strtolower((string) $this->argument('wave'));
    $endpointId = (int) $this->argument('cliente_endpoint_id');
    $emailTo = (string) $this->option('test');
    $op      = (string) $this->option('op') ?: 'maquila';

    if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
        $this->error("Wave inválida: {$wave}"); return self::FAILURE;
    }
    if (!$emailTo) { $this->error('--test es obligatorio.'); return self::FAILURE; }

    $contact = findContactByEndpoint($endpointId);
    if (!$contact) { $this->error("No hay contacto para endpoint {$endpointId}."); return self::FAILURE; }

    $name      = (string) ($this->option('name') ?: ($contact->contact_name ?? 'Cliente'));
    $surveyUrl = config('rebranding.survey_url');

    Mail::to($emailTo)->send(new SurveyNudgeMail(
        name:       $name,
        operation:  $op,
        surveyUrl:  $surveyUrl,
        wave:       $wave
    ));

    $this->info("Preview wave={$wave} enviado a {$emailTo} (endpoint={$endpointId}, op={$op}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW de recordatorios a un correo de prueba');
