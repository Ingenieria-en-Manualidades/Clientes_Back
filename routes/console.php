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
function findClientByEndpointWithOptionalUser(int $endpointId): ?object {
    $base = DB::table('clientes as c')
        ->where('c.cliente_endpoint_id', $endpointId);

    // Preferimos 'cliente_user' si existe
    if (Schema::hasTable('cliente_user')) {
        return $base
            ->leftJoin('cliente_user as cu', 'cu.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'u.id', '=', 'cu.user_id')
            ->select([
                'c.cliente_endpoint_id',
                'c.nombre',
                DB::raw('u.email as user_email'),
            ])
            ->first();
    }

    // Alternativa: 'user_clients'
    if (Schema::hasTable('user_clients')) {
        return $base
            ->leftJoin('user_clients as uc', 'uc.client_id', '=', 'c.id')
            ->leftJoin('users as u', 'u.id', '=', 'uc.user_id')
            ->select([
                'c.cliente_endpoint_id',
                'c.nombre',
                DB::raw('u.email as user_email'),
            ])
            ->first();
    }

    // Sin pivote: solo datos del cliente
    return $base->select([
        'c.cliente_endpoint_id',
        'c.nombre',
    ])->first();
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
    $emailTo    = (string) $this->option('test');     // OBLIGATORIO
    $op         = (string) $this->option('op') ?: 'maquila';

    if (!$emailTo) {
        $this->error('--test es obligatorio (correo de prueba).');
        return self::FAILURE;
    }

    $row = findClientByEndpointWithOptionalUser($endpointId);
    if (!$row) {
        $this->error("Cliente con cliente_endpoint_id={$endpointId} no encontrado.");
        return self::FAILURE;
    }

    $name       = (string) ($this->option('name') ?: ($row->nombre ?? 'Cliente'));
    $surveyUrl  = config('rebranding.survey_url');
    $contactEmail = $row->user_email ?? null; // opcional, solo para el footer si lo usas

    // Envío inmediato (sin colas) para la prueba
    Mail::to($emailTo)->send(new RebrandingMail(
        name:         $name,
        operation:    $op,
        surveyUrl:    $surveyUrl,
        // sin teléfono:
        contactEmail: $contactEmail
    ));

    $this->info("Preview enviado a {$emailTo} para cliente_endpoint_id={$endpointId} (op={$op}) con nombre=\"{$name}\"");
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
    $wave       = strtolower((string) $this->argument('wave'));
    $endpointId = (int) $this->argument('cliente_endpoint_id');
    $emailTo    = (string) $this->option('test');
    $op         = (string) $this->option('op') ?: 'maquila';

    if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
        $this->error("Wave inválida: {$wave}");
        return self::FAILURE;
    }
    if (!$emailTo) {
        $this->error('--test es obligatorio (correo de prueba).');
        return self::FAILURE;
    }

    $row = findClientByEndpointWithOptionalUser($endpointId);
    if (!$row) {
        $this->error("Cliente con cliente_endpoint_id={$endpointId} no encontrado.");
        return self::FAILURE;
    }

    $name      = (string) ($this->option('name') ?: ($row->nombre ?? 'Cliente'));
    $surveyUrl = config('rebranding.survey_url');

    Mail::to($emailTo)->send(new SurveyNudgeMail(
        name:      $name,
        operation: $op,
        surveyUrl: $surveyUrl,
        wave:      $wave
    ));

    $this->info("Preview wave={$wave} enviado a {$emailTo} (cliente_endpoint_id={$endpointId}, op={$op}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW de recordatorios a un correo de prueba');
