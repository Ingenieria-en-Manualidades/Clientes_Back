<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Mail\RebrandingMail;
use App\Mail\SurveyNudgeMail;

/** Demo por defecto */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Normaliza descripción a slug de operación
 */
function op_to_slug(?string $txt): string {
    $t = trim(mb_strtolower((string)$txt));
    $map = [
        'maquila' => 'maquila',
        'logistica' => 'logistica',
        'logística' => 'logistica',
        'manufactura' => 'manufactura',
        'aeropuertos' => 'aeropuertos',
        'zona franca' => 'zona_franca',
        'soluciones' => 'soluciones',
        'soluciones especializadas' => 'soluciones',
    ];
    foreach ($map as $needle => $slug) {
        if (str_contains($t, $needle)) return $slug;
    }
    return 'maquila';
}

/**
 * Trae UN contacto relacionado al cliente_endpoint_id:
 *  clientes c → cliente_user cu → users u → último surveys.customer_contact cc (por user_id)
 *  operación por cliente: surveys.type_operation_has_clients toc → surveys.type_operation to
 */
function findContactByEndpoint(int $endpointId): ?object {
    // subconsulta: último cc por user_id
    $latestCc = DB::table('surveys.customer_contact as cc1')
        ->select('cc1.user_id', DB::raw('MAX(cc1.customer_contact_id) as last_cc_id'))
        ->groupBy('cc1.user_id');

    return DB::table('clientes as c')
        ->join('cliente_user as cu', 'cu.cliente_id', '=', 'c.id')
        ->join('users as u', 'u.id', '=', 'cu.user_id')
        ->joinSub($latestCc, 'last_cc', function ($j) {
            $j->on('last_cc.user_id', '=', 'u.id');
        })
        ->join('surveys.customer_contact as cc', function ($j) {
            $j->on('cc.user_id', '=', 'u.id')
              ->on('cc.customer_contact_id', '=', 'last_cc.last_cc_id');
        })
        ->leftJoin('surveys.type_operation_has_clients as toc', 'toc.clients_id', '=', 'c.id')
        ->leftJoin('surveys.type_operation as to', 'to.type_operation_id', '=', 'toc.type_operation_id')
        ->where('c.cliente_endpoint_id', $endpointId)
        ->whereNotNull('cc.email')
        ->orderByDesc('cc.customer_contact_id')
        ->select([
            'c.id           as client_id',
            'c.cliente_endpoint_id',
            'u.id           as user_id',
            'u.name         as username',       // este es el "usuario" a compartir
            'c.nombre       as client_name',
            'cc.fullname    as contact_name',
            'cc.email       as contact_email',
            'cc.cellphone   as contact_phone',
            'to.description as operation_desc', // descripción de operación
        ])
        ->first();
}


/**
 * PREVIEW: Día 0 (rebranding) a un correo de prueba (NO masivo)
 *
 * Uso:
 *  php artisan rebranding:preview {cliente_endpoint_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 * --op es opcional: si no se pasa, se toma de surveys.type_operation.description
 */
Artisan::command('rebranding:preview {cliente_endpoint_id} {--test=} {--op=} {--name=}', function () {
    $endpointId = (int) $this->argument('cliente_endpoint_id');
    $emailTo    = (string) $this->option('test');  // obligatorio para preview
    $opOpt      = (string) ($this->option('op') ?? '');
    $nameOpt    = (string) ($this->option('name') ?? '');

    if (!$emailTo) { $this->error('--test es obligatorio.'); return self::FAILURE; }

    $contact = findContactByEndpoint($endpointId);
    if (!$contact) { $this->error("No hay contacto para endpoint {$endpointId}."); return self::FAILURE; }

    $name       = $nameOpt !== '' ? $nameOpt : ($contact->contact_name ?? 'Cliente');
    $operation  = $opOpt !== '' ? $opOpt : op_to_slug($contact->operation_desc ?? null);
    $surveyUrl  = config('rebranding.survey_url');

    // credenciales a compartir en el correo
    $username   = $contact->username ?? null;  // users.name
    $tmpPass    = 'Temporal01';

    Mail::to($emailTo)->send(new RebrandingMail(
        name:          $name,
        operation:     $operation,
        surveyUrl:     $surveyUrl,
        contactEmail:  $contact->contact_email, // por si la plantilla lo usa
        contactPhone:  $contact->contact_phone,
        username:      $username,
        tempPassword:  $tmpPass
    ));

    $this->info("Preview enviado a {$emailTo} (endpoint={$endpointId}, op={$operation}, usuario={$username}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW del Día 0 a un correo de prueba');


/**
 * PREVIEW: Recordatorios (waves) a un correo de prueba (NO masivo)
 *
 * Uso:
 *  php artisan survey:nudge:preview {wave} {cliente_endpoint_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 * wave: day3|day7|thanks|day14|nov|dec_mid
 */
Artisan::command('survey:nudge:preview {wave} {cliente_endpoint_id} {--test=} {--op=} {--name=}', function () {
    $wave      = strtolower((string) $this->argument('wave'));
    if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
        $this->error("Wave inválida: {$wave}"); return self::FAILURE;
    }

    $endpointId = (int) $this->argument('cliente_endpoint_id');
    $emailTo    = (string) $this->option('test');
    $opOpt      = (string) ($this->option('op') ?? '');
    $nameOpt    = (string) ($this->option('name') ?? '');
    if (!$emailTo) { $this->error('--test es obligatorio.'); return self::FAILURE; }

    $contact = findContactByEndpoint($endpointId);
    if (!$contact) { $this->error("No hay contacto para endpoint {$endpointId}."); return self::FAILURE; }

    $name      = $nameOpt !== '' ? $nameOpt : ($contact->contact_name ?? 'Cliente');
    $operation = $opOpt !== '' ? $opOpt : op_to_slug($contact->operation_desc ?? null);
    $surveyUrl = config('rebranding.survey_url');

    Mail::to($emailTo)->send(new SurveyNudgeMail(
        name:       $name,
        operation:  $operation,
        surveyUrl:  $surveyUrl,
        wave:       $wave
    ));

    $this->info("Preview wave={$wave} enviado a {$emailTo} (endpoint={$endpointId}, op={$operation}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW de recordatorios a un correo de prueba');



use App\Services\CampaignService;

/**
 * ENVÍO MASIVO – Día 0 (rebranding)
 * Uso: php artisan rebranding:send {campaign} {--limit=0} {--dry}
 * Ej.: php artisan rebranding:send 2025D0 --limit=200
 */
Artisan::command('rebranding:send {campaign} {--limit=0} {--dry}', function (CampaignService $svc) {
    $campaign = (string)$this->argument('campaign');
    $limit    = (int)$this->option('limit');
    $dry      = (bool)$this->option('dry');

    $res = $svc->sendRebranding($campaign, $limit, $dry);
    $this->info("Rebranding " . ($dry ? '(dry-run) ' : '') . "→ sent={$res['sent']} / total={$res['total']}");
})->purpose('Enviar masivo Día 0 a contactos relacionados');


/**
 * ENVÍO MASIVO – Recordatorios (waves)
 * Uso: php artisan survey:nudge:send {wave} {campaign} {--limit=0} {--dry} {--op=}
 * wave: day3|day7|thanks|day14|nov|dec_mid
 * Ej.: php artisan survey:nudge:send day3 2025D0 --limit=500
 */
Artisan::command('survey:nudge:send {$campaign}:{$wave} {--limit=0} {--dry} {--op=}', function (CampaignService $svc) {
    $wave     = strtolower((string)$this->argument('wave'));
    $campaign = (string)$this->argument('campaign');
    $limit    = (int)$this->option('limit');
    $dry      = (bool)$this->option('dry');
    $forceOp  = (string)$this->option('op') ?: null;

    if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
        $this->error("Wave inválida: {$wave}");
        return self::FAILURE;
    }

    $res = $svc->sendNudge($wave, $campaign, $limit, $dry, $forceOp);
    $this->info("Nudge {$wave} " . ($dry ? '(dry-run) ' : '') . "→ sent={$res['sent']} skipped={$res['skipped']} / total={$res['total']}");
})->purpose('Enviar masivo de recordatorios');
