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
 * Helper: trae el registro del cliente y (si existen tablas) el primer usuario relacionado
 * para obtener teléfono/email de contacto desde users.
 */
function findClientWithOptionalUser(int $clientId): ?object {
    // Base: clientes.id + clientes.nombre
    $base = DB::table('clientes as c')->where('c.id', $clientId);

    // Si existe 'cliente_user', preferimos esa
    if (Schema::hasTable('cliente_user')) {
        return $base
            ->leftJoin('cliente_user as cu', 'cu.cliente_id', '=', 'c.id')
            ->leftJoin('users as u', 'u.id', '=', 'cu.user_id')
            ->select([
                'c.id',
                'c.nombre',
                DB::raw('u.email as user_email'),
                // Algunas instalaciones usan cellphone, otras telefono: tomamos ambas
                DB::raw('u.cellphone as user_cellphone'),
                DB::raw('u.telefono as user_telefono'),
            ])
            ->first();
    }

    // Si no, probamos 'user_clients'
    if (Schema::hasTable('user_clients')) {
        return $base
            ->leftJoin('user_clients as uc', 'uc.client_id', '=', 'c.id')
            ->leftJoin('users as u', 'u.id', '=', 'uc.user_id')
            ->select([
                'c.id',
                'c.nombre',
                DB::raw('u.email as user_email'),
                DB::raw('u.cellphone as user_cellphone'),
                DB::raw('u.telefono as user_telefono'),
            ])
            ->first();
    }

    // Si no hay pivote, solo devolvemos el cliente
    return $base->select(['c.id', 'c.nombre'])->first();
}


/**
 * PREVIEW: Día 0 (lanzamiento) a un destinatario de prueba
 *
 * Uso:
 *  php artisan rebranding:preview {client_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 *  - client_id  : id del cliente en la tabla `clientes`
 *  - --test     : correo destino (OBLIGATORIO)
 *  - --op       : logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones (default: maquila)
 *  - --name     : si quieres forzar el nombre mostrado (sino toma `clientes.nombre`)
 */
Artisan::command('rebranding:preview {client_id} {--test=} {--op=maquila} {--name=}', function () {
    $clientId = (int) $this->argument('client_id');
    $emailTo  = (string) $this->option('test');     // OBLIGATORIO
    $op       = (string) $this->option('op') ?: 'maquila';

    if (!$emailTo) {
        $this->error('--test es obligatorio (correo de prueba).');
        return self::FAILURE;
    }

    $row = findClientWithOptionalUser($clientId);
    if (!$row) {
        $this->error("Cliente {$clientId} no encontrado.");
        return self::FAILURE;
    }

    $name = (string) ($this->option('name') ?: ($row->nombre ?? 'Cliente'));
    $surveyUrl = config('rebranding.survey_url');

    // Campos opcionales de contacto (no afectan el envío; van al footer del correo si existen)
    $contactPhone = $row->user_cellphone ?? $row->user_telefono ?? null;
    $contactEmail = $row->user_email ?? null;

    // Envío inmediato (sin colas) para la prueba
    Mail::to($emailTo)->send(new RebrandingMail(
        name:         $name,
        operation:    $op,
        surveyUrl:    $surveyUrl,
        contactPhone: $contactPhone,
        contactEmail: $contactEmail
    ));

    $this->info("Preview enviado a {$emailTo} para client_id={$clientId} (op={$op}) con nombre=\"{$name}\"");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW del Día 0 a un correo de prueba');


/**
 * PREVIEW: Recordatorios (waves) a un destinatario de prueba
 *
 * Uso:
 *  php artisan survey:nudge:preview {wave} {client_id} --test=correo@dominio --op=maquila --name="Nombre opcional"
 *
 *  - wave       : day3|day7|thanks|day14|nov|dec_mid
 *  - client_id  : id del cliente en `clientes`
 *  - --test     : correo destino (OBLIGATORIO)
 *  - --op       : logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones (default: maquila)
 *  - --name     : fuerza el nombre mostrado (si no, usa `clientes.nombre`)
 */
Artisan::command('survey:nudge:preview {wave} {client_id} {--test=} {--op=maquila} {--name=}', function () {
    $wave     = strtolower((string) $this->argument('wave'));
    $clientId = (int) $this->argument('client_id');
    $emailTo  = (string) $this->option('test');
    $op       = (string) $this->option('op') ?: 'maquila';

    if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
        $this->error("Wave inválida: {$wave}");
        return self::FAILURE;
    }
    if (!$emailTo) {
        $this->error('--test es obligatorio (correo de prueba).');
        return self::FAILURE;
    }

    $row = findClientWithOptionalUser($clientId);
    if (!$row) {
        $this->error("Cliente {$clientId} no encontrado.");
        return self::FAILURE;
    }

    $name = (string) ($this->option('name') ?: ($row->nombre ?? 'Cliente'));
    $surveyUrl = config('rebranding.survey_url');

    Mail::to($emailTo)->send(new SurveyNudgeMail(
        name:      $name,
        operation: $op,
        surveyUrl: $surveyUrl,
        wave:      $wave
    ));

    $this->info("Preview wave={$wave} enviado a {$emailTo} (client_id={$clientId}, op={$op}, nombre=\"{$name}\")");
    return self::SUCCESS;
})->purpose('Enviar PREVIEW de recordatorios a un correo de prueba');
