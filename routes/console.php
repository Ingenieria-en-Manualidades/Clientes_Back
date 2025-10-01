<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\RebrandingMail;

/**
 * Envío de correos por cambio de imagen (segmentado por tipo de operación)
 *
 * Uso:
 *  php artisan rebranding:send --type=all|logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones
 *                              --limit=0 --dry-run --test=correo@dominio.com --campaign=rebranding_v1
 *
 * --type     : filtra por tipo de operación (acepta variantes, p. ej. aeropuertos/aeropuerto)
 * --limit    : limita cantidad de destinatarios (debug)
 * --dry-run  : lista destinatarios sin enviar ni registrar bitácora
 * --test     : rutea TODOS los envíos al correo indicado (pruebas)
 * --campaign : nombre de campaña para bitácora (único por email)
 */
Artisan::command('rebranding:send {--type=all} {--limit=0} {--dry-run} {--test=} {--campaign=rebranding_v1}', function () {
    $type     = strtolower($this->option('type') ?? 'all');
    $limit    = (int) ($this->option('limit') ?? 0);
    $dry      = (bool) $this->option('dry-run');
    $test     = $this->option('test');
    $campaign = (string) $this->option('campaign');

    $valid = ['logistica','manufactura','maquila','aeropuertos','zona_franca','soluciones','all'];
    if (!in_array($type, $valid, true)) {
        $this->error('Tipo inválido. Use: '.implode('|', $valid));
        return 1;
    }

    // Variantes válidas por tipo (minúsculas)
    $variants = function (string $t) {
        return match ($t) {
            'maquila'     => ['maquila','maqui'],
            'aeropuertos' => ['aeropuerto','aeropuertos'],
            'logistica'   => ['logistica','logística'],
            'zona_franca' => ['zona franca','zona_franca','zf'],
            'soluciones'  => ['soluciones','soluciones especializadas','soluciones_especializadas'],
            default       => [$t],
        };
    };

    // Canoniza descripción -> slug para la plantilla
    $canonical = function (?string $desc) {
        $s = Str::of((string)$desc)->lower()->ascii()->replace(['-', ' '], ['_', '_'])->value();
        return match (true) {
            str_contains($s, 'aeropuerto')                       => 'aeropuertos',
            str_contains($s, 'maqui')                            => 'maquila',
            str_contains($s, 'logistica')                        => 'logistica',
            str_contains($s, 'manufactur')                       => 'manufactura',
            (str_contains($s, 'zona') && str_contains($s, 'franca')) => 'zona_franca',
            str_contains($s, 'solucion')                         => 'soluciones',
            default                                              => 'generico',
        };
    };

    // ---------- Subquery: elegir 1 usuario preferente por cliente ----------
    // Criterios de orden (mejor primero):
    //   1) Tiene email (no nulo/ vacío)
    //   2) u.activo en ('s', true, 't', 'true', '1')
    //   3) Menor u.id (determinístico)
    $uSub = DB::table('clients.cliente_user as cu')
        ->join('clients.users as u', 'u.id', '=', 'cu.user_id')
        ->whereNull('cu.deleted_at')
        ->whereNull('u.deleted_at')
        ->selectRaw("
            cu.cliente_id,
            u.id   as user_id,
            u.name as contact_name,
            u.email,
            ROW_NUMBER() OVER (
                PARTITION BY cu.cliente_id
                ORDER BY
                    CASE WHEN u.email IS NULL OR u.email = '' THEN 1 ELSE 0 END,
                    CASE
                      WHEN COALESCE(TRIM(LOWER(u.activo::text)),'') IN ('s','t','true','1') THEN 0
                      ELSE 1
                    END,
                    u.id
            ) as rn
        ");

    // -------------------- Query principal --------------------
    $q = DB::table('surveys.type_operation_has_clients as toc')
        ->join('surveys.type_operation as t', 't.type_operation_id', '=', 'toc.type_operation_id')
        ->join('clients.clientes as c', 'c.cliente_endpoint_id', '=', 'toc.clients_id')

        // Un solo usuario por cliente (uu.rn = 1)
        ->leftJoinSub($uSub, 'uu', function ($j) {
            $j->on('uu.cliente_id', '=', 'c.cliente_endpoint_id')
              ->where('uu.rn', '=', 1);
        })

        // Borrado lógico
        ->whereNull('t.deleted_at')
        ->whereNull('toc.deleted_at')
        ->whereNull('c.deleted_at')

        // Activos (soporta bool o char(1))
        ->whereRaw("COALESCE(TRIM(LOWER(t.active::text)),'') IN ('t','true','1','s')")
        ->whereRaw("COALESCE(TRIM(LOWER(c.activo::text)),'') IN ('s','t','true','1')");

    // Filtro por --type (sin problemas de bindings)
    if ($type !== 'all') {
        $vals = array_values(array_unique(array_map('strtolower', $variants($type))));
        $q->where(function ($qq) use ($vals) {
            foreach ($vals as $i => $v) {
                $i === 0
                    ? $qq->whereRaw('LOWER(t.description) = ?', [$v])
                    : $qq->orWhereRaw('LOWER(t.description) = ?', [$v]);
            }
        });
    }

    // Campos de salida (desde uu.* ya depurado)
    $q->select([
        'c.cliente_endpoint_id as client_id',
        'c.nombre as client_name',
        't.description as operation_desc',
        'uu.user_id',
        DB::raw("COALESCE(uu.contact_name, c.nombre) as contact_name"),
        DB::raw("uu.email as email"),
    ]);

    if ($limit > 0) {
        $q->limit($limit);
    }

    $rows = $q->orderBy('c.cliente_endpoint_id')->get();

    if ($rows->isEmpty()) {
        $this->warn('No se encontraron destinatarios.');
        return 0;
    }

    $surveyUrl = config('rebranding.survey_url');
    $count = 0;
    $seen  = []; // deduplicación por email

    foreach ($rows as $r) {
        $email = $test ?: $r->email;
        $name  = $r->contact_name ?: 'Cliente';
        $op    = $canonical($r->operation_desc);

        // Validación de email
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->warn("SKIP: email inválido para {$name} (user_id={$r->user_id}, cliente={$r->client_id})");
            continue;
        }
        // Evitar duplicados
        if (isset($seen[$email])) {
            $this->warn("SKIP: duplicado {$email}");
            continue;
        }

        // Evitar reenvío por campaña (si existe tabla mail_logs)
        $hasMailLogs = true;
        try {
            DB::table('mail_logs')->select('id')->limit(1)->get();
        } catch (\Throwable $e) {
            $hasMailLogs = false;
        }

        if ($hasMailLogs && !$dry) {
            $already = DB::table('mail_logs')
                ->where('campaign', $campaign)
                ->where('email', $email)
                ->exists();
            if ($already) {
                $this->warn("SKIP: ya registrado {$email} ({$campaign})");
                continue;
            }
        }

        $seen[$email] = true;
        $count++;
        $this->info("[#{$count}] {$email} | {$name} | op={$op} | cliente_id={$r->client_id}" . ($r->user_id ? " | user_id={$r->user_id}" : ''));

        if (!$dry) {
            // Encolar correo
            Mail::to($email)->queue(new RebrandingMail(
                name:      $name,
                email:     $email,
                operation: $op,
                surveyUrl: $surveyUrl
            ));

            // Registrar bitácora si existe tabla
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
        }
    }

    $this->info($dry ? "Listado: {$count}" : "Encolados: {$count}");
    return 0;
})->purpose('Enviar correos de rebranding segmentados por tipo de operación');


Artisan::command('rebranding:preview {client_id} {--test=}', function () {
    $clientId = (int) $this->argument('client_id');
    $test     = $this->option('test');

    // Trae un cliente + su tipo + un usuario (mismo subquery de rn=1 que usamos en send)
    $row = DB::table('clients.clientes as c')
        ->join('surveys.type_operation_has_clients as toc', 'toc.clients_id', '=', 'c.cliente_endpoint_id')
        ->join('surveys.type_operation as t', 't.type_operation_id', '=', 'toc.type_operation_id')
        ->leftJoinSub(
            DB::table('clients.cliente_user as cu')
              ->join('clients.users as u', 'u.id', '=', 'cu.user_id')
              ->whereNull('cu.deleted_at')->whereNull('u.deleted_at')
              ->selectRaw("
                cu.cliente_id,
                u.id as user_id,
                u.name as contact_name,
                u.email,
                ROW_NUMBER() OVER (
                  PARTITION BY cu.cliente_id
                  ORDER BY
                    CASE WHEN u.email IS NULL OR u.email = '' THEN 1 ELSE 0 END,
                    CASE WHEN COALESCE(TRIM(LOWER(u.activo::text)),'') IN ('s','t','true','1') THEN 0 ELSE 1 END,
                    u.id
                ) as rn
              "),
            'uu',
            fn($j) => $j->on('uu.cliente_id', '=', 'c.cliente_endpoint_id')->where('uu.rn', '=', 1)
        )
        ->where('c.cliente_endpoint_id', $clientId)
        ->select([
            'c.cliente_endpoint_id as client_id',
            'c.nombre as client_name',
            't.description as operation_desc',
            'uu.user_id',
            DB::raw("COALESCE(uu.contact_name, c.nombre) as contact_name"),
            DB::raw("uu.email as email"),
        ])
        ->first();

    if (!$row) {
        $this->error("No encontré cliente_id={$clientId}");
        return 1;
    }

    $slug = (function (?string $desc) {
        $s = \Illuminate\Support\Str::of((string)$desc)->lower()->ascii()->replace(['-', ' '], ['_', '_'])->value();
        return match (true) {
            str_contains($s, 'aeropuerto') => 'aeropuertos',
            str_contains($s, 'maqui') => 'maquila',
            str_contains($s, 'logistica') => 'logistica',
            str_contains($s, 'manufactur') => 'manufactura',
            (str_contains($s, 'zona') && str_contains($s, 'franca')) => 'zona_franca',
            str_contains($s, 'solucion') => 'soluciones',
            default => 'generico',
        };
    })($row->operation_desc);

    $mail = new RebrandingMail(
        name: $row->contact_name ?: $row->client_name,
        email: $row->email ?: 'sin-correo@local.test',
        operation: $slug,
        surveyUrl: config('rebranding.survey_url')
    );

    if ($test) {
        \Illuminate\Support\Facades\Mail::to($test)->send($mail);
        $this->info("Enviado preview a {$test} para client_id={$row->client_id} (op={$slug})");
    } else {
        // Render en texto para inspección rápida
        $this->line($mail->render());
    }

    return 0;
})->purpose('Previsualiza el correo para un cliente (opcionalmente lo envía a --test)');