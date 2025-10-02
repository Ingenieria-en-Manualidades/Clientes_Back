<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Mail\RebrandingMail;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Aquí puedes registrar comandos basados en closures. Para comandos más
| complejos, crea una clase en app/Console/Commands.
*/

/** Comando de ejemplo de Laravel */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Utilidad: normaliza la descripción del área/operación a un slug conocido
 * Soporta: maquila, logística, manufactura, aeropuertos, zona franca, soluciones
 */
if (!function_exists('op_to_slug')) {
    function op_to_slug(?string $txt): string
    {
        $t = Str::of((string) $txt)->lower()->squish()->toString();

        $map = [
            'maquila'       => 'maquila',
            'logistica'     => 'logistica',
            'logística'     => 'logistica',
            'manufactura'   => 'manufactura',
            'aeropuertos'   => 'aeropuertos',
            'zona franca'   => 'zona_franca',
            'soluciones'    => 'soluciones',
            'soluciones especializadas' => 'soluciones',
        ];

        foreach ($map as $needle => $slug) {
            if (Str::contains($t, $needle)) return $slug;
        }

        // por defecto usa maquila (ajusta si deseas otro default)
        return 'maquila';
    }
}

/**
 * PREVIEW
 *  Ejemplo:
 *    php artisan rebranding:preview 220 --test=auxiliar1.tecnologia-op@ienm.com.co
 *    php artisan rebranding:preview 220 --test=mail@dominio.com --op=logistica --dry
 */
Artisan::command('rebranding:preview {client_id} {--test=} {--op=} {--dry}', function () {
    $clientId   = (int) $this->argument('client_id');
    $testEmail  = (string) $this->option('test');
    $overrideOp = (string) ($this->option('op') ?? '');
    $dry        = (bool) $this->option('dry');

    if (!$testEmail) {
        $this->error('Debes indicar un email de prueba con --test=correo@dominio.com');
        return self::FAILURE;
    }

    // === Ajusta esta consulta a tus tablas reales ===
    // Debe obtener (al menos): client_name, contact_name, email, operation_desc
    $row = DB::table('clientes as c')
        // ->leftJoin('contactos as ct', 'ct.cliente_id', '=', 'c.id') // si aplica
        // ->leftJoin('cliente_operaciones as co', 'co.cliente_id', '=', 'c.id') // si aplica
        ->selectRaw('?::int as client_id', [$clientId]) // por si no hay join real
        ->selectRaw('c.nombre as client_name')
        ->selectRaw('c.nombre as contact_name') // cambia por el nombre del contacto si lo tienes
        ->selectRaw('? as email', [$testEmail]) // para preview usamos el test email
        ->selectRaw('? as operation_desc', ['maquila']) // <-- cámbialo si tienes el campo real
        ->where('c.id', $clientId)
        ->first();

    // Si no existe en la consulta, usa valores por defecto
    $name = $row->contact_name ?? $row->client_name ?? "CLIENTE {$clientId}";
    $op   = $overrideOp ?: op_to_slug($row->operation_desc ?? null);

    $surveyUrl = config('rebranding.survey_url', 'https://cim.ienm.com.co/encuesta/');

    if ($dry) {
        $this->line("DRY RUN → Enviaría preview a {$testEmail} para client_id={$clientId} (op={$op})");
        return self::SUCCESS;
    }

    // Envío directo (no cola) para validar visual
    Mail::to($testEmail)->send(new RebrandingMail(
        name:      $name,
        operation: $op,
        surveyUrl: $surveyUrl
    ));

    $this->info("Enviado preview a {$testEmail} para client_id={$clientId} (op={$op})");
    return self::SUCCESS;
})->purpose('Enviar un correo de preview del rebranding a un email de prueba');

/**
 * ENVÍO MASIVO / PRODUCCIÓN
 *  Ejemplo:
 *    php artisan rebranding:send --campaign=rebranding-2025 --limit=100
 *    php artisan rebranding:send --dry
 */
Artisan::command('rebranding:send {--campaign=rebranding-2025} {--limit=0} {--op=} {--dry}', function () {
    $campaign   = (string) $this->option('campaign');
    $limit      = (int)    $this->option('limit');
    $overrideOp = (string) ($this->option('op') ?? '');
    $dry        = (bool)   $this->option('dry');

    $hasMailLogs = Schema::hasTable('mail_logs');

    // === Ajusta esta consulta a tus tablas reales ===
    // Debe devolver: client_id, user_id (si aplica), client_name, contact_name, email, operation_desc
    $q = DB::table('clientes as c')
        // ->join('contactos as ct','ct.cliente_id','=','c.id') // si aplica
        // ->leftJoin('cliente_operaciones as co','co.cliente_id','=','c.id')
        ->select([
            'c.id as client_id',
            DB::raw('NULL::int as user_id'),   // ajusta si corresponde
            'c.nombre as client_name',
            'c.nombre as contact_name',        // ajusta si tienes contacto
            DB::raw("c.email as email"),       // ajusta campo real del email del contacto
            DB::raw("'maquila' as operation_desc"), // ajusta con tu campo real
        ])
        ->whereNotNull('c.email'); // filtra los que tengan email (ajusta)

    if ($limit > 0) $q->limit($limit);

    $rows = $q->get();

    $count = 0;
    foreach ($rows as $r) {
        $email = $r->email;
        if (!$email) continue;

        $name = $r->contact_name ?: $r->client_name ?: 'Cliente';
        $op   = $overrideOp ?: op_to_slug($r->operation_desc ?? null);

        $surveyUrl = config('rebranding.survey_url', 'https://cim.ienm.com.co/encuesta/');

        if ($dry) {
            $this->line("DRY RUN → Queue {$email} ({$name}) op={$op}");
            $count++;
            continue;
        }

        // Encolar correo (recomendado en producción)
        Mail::to($email)->queue(new RebrandingMail(
            name:      $name,
            operation: $op,
            surveyUrl: $surveyUrl
        ));

        if ($hasMailLogs) {
            DB::table('mail_logs')->insert([
                'campaign'   => $campaign,
                'client_id'  => $r->client_id,
                'user_id'    => $r->user_id,
                'email'      => $email,
                'status'     => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $count++;
    }

    $this->info("Procesados {$count} destinatarios. " . ($dry ? '(DRY RUN)' : 'Encolados.'));
    $this->info("Recuerda tener activo el worker: php artisan queue:work --queue=default -v");
    return self::SUCCESS;
})->purpose('Envío masivo del correo de rebranding (cola + bitácora opcional)');
