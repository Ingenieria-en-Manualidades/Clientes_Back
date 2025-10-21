<?php

namespace App\Services;

use App\Mail\RebrandingMail;
use App\Mail\SurveyNudgeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignService
{
    /* ------------------------------------------
     * Helpers
     * ----------------------------------------*/
    public function opToSlug(?string $txt): string
    {
        $t = Str::of((string)$txt)->lower()->squish()->toString();
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
            if (Str::contains($t, $needle)) return $slug;
        }
        return 'maquila';
    }

    public function hasResponded(string $email): bool
    {
        // Ajusta si tienes tabla real de respuestas:
        // return DB::table('surveys.survey_responses')->where('email', $email)->exists();
        return false;
    }

    /**
     * Base: contactos RELACIONADOS al cliente por cliente_user -> users -> surveys.customer_contact
     * - Un contacto por user (el último por customer_contact_id)
     * - Sólo con email
     */
    protected function relatedContactsQuery()
    {
        // último contacto por user_id
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
            // operación por cliente
            ->leftJoin('surveys.type_operation_has_clients as toc', 'toc.clients_id', '=', 'c.id')
            ->leftJoin('surveys.type_operation as to', 'to.type_operation_id', '=', 'toc.type_operation_id')

            ->whereNotNull('cc.email')
            ->select([
                'c.id         as client_id',
                'u.id         as user_id',
                'u.name       as username',        // username correcto
                'c.nombre     as client_name',
                'cc.fullname  as contact_name',
                'cc.email     as contact_email',
                'cc.cellphone as contact_phone',
                'to.description as operation_desc', // <-- viene de surveys.type_operation
            ]);
    }


    /* ------------------------------------------
     * Día 0: Rebranding (MASIVO sólo relacionados)
     * ----------------------------------------*/
    public function sendRebranding(string $campaign, int $limit = 0, bool $dry = false): array
    {
        $hasMailLogs = Schema::hasTable('surveys.mail_logs');

        $q = $this->relatedContactsQuery();

        // if ($hasMailLogs) {
        //     $q->whereNotExists(function ($s) use ($campaign) {
        //         $s->select(DB::raw(1))
        //             ->from('surveys.mail_logs as ml')
        //             ->whereColumn('ml.email', 'cc.email')
        //             ->where('ml.campaign', $campaign);
        //     });
        // }

        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $sent = 0;

        foreach ($rows as $r) {
            $email = $r->contact_email;
            if (!$email) continue;

            $name = $r->contact_name ?: $r->client_name ?: 'Cliente';
            $op   = $this->opToSlug($r->operation_desc ?? null);
            $username = $r->username ?? null;
            $tmpPass  = 'Temporal01';

            if (!$dry) {
                Mail::to($email)->queue(new RebrandingMail(
                    name: $name,
                    operation: $op,
                    surveyUrl: $surveyUrl,
                    contactPhone: $r->contact_phone,
                    username: $username,
                    tempPassword: $tmpPass,
                    contactEmail: $email
                ));

                if ($hasMailLogs) {
                    DB::table('surveys.mail_logs')->insert([
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

            $sent++;
        }

        return ['sent' => $sent, 'total' => count($rows)];
    }

    /* ------------------------------------------
     * Recordatorios (waves)
     * ----------------------------------------*/
    public function sendNudge(string $wave, string $campaign, int $limit = 0, bool $dry = false, ?string $forceOp = null): array
{
    $hasMailLogs  = Schema::hasTable('surveys.mail_logs');
    $waveCampaign = "{$campaign}:{$wave}";

    $q = DB::table('surveys.mail_logs as ml')
        ->join('surveys.customer_contact as cc', 'cc.email', '=', 'ml.email')
        ->leftJoin('cliente_user as cu', 'cu.user_id', '=', 'cc.user_id')
        ->leftJoin('clientes as c', 'c.id', '=', 'cu.cliente_id')
        ->leftJoin('surveys.type_operation_has_clients as toc', 'toc.clients_id', '=', 'c.id')
        ->leftJoin('surveys.type_operation as to', 'to.type_operation_id', '=', 'toc.type_operation_id')
        // base: enviados en la campaña original
        ->where('ml.campaign', $campaign)
        // no repetir el mismo wave
        // ->whereNotExists(function ($s) use ($waveCampaign) {
        //     $s->select(DB::raw(1))
        //       ->from('surveys.mail_logs as ml2')
        //       ->whereColumn('ml2.email', 'ml.email')
        //       ->where('ml2.campaign', $waveCampaign);
        // })
        // solo a quienes no han diligenciado la encuesta activa
        ->whereNotExists(function ($s) {
            $s->select(DB::raw(1))
              ->from('surveys.customer_contact_has_survey as cchs')
              ->join('surveys.survey as sv', 'sv.survey_id', '=', 'cchs.survey_id')
              ->whereColumn('cchs.customer_contact_id', 'cc.customer_contact_id')
              ->whereNull('cchs.deleted_at')
              ->where('cchs.active', true)
              ->where('sv.active', true);
        })
        ->select([
            DB::raw('coalesce(c.id, 0) as client_id'),
            'cc.user_id  as user_id',
            'cc.fullname as contact_name',
            'ml.email    as email',
            'to.description as operation_desc',
        ])
        // evita duplicados por joins
        ->groupBy('c.id', 'cc.user_id', 'cc.fullname', 'ml.email', 'to.description');

    if ($limit > 0) $q->limit($limit);
    $rows = $q->get();

    $surveyUrl = config('rebranding.survey_url');
    $sent = 0;
    $skipped = 0;

    foreach ($rows as $r) {
        $email = $r->email;
        if (!$email) { $skipped++; continue; }

        $name = $r->contact_name ?: 'Cliente';
        $op   = $forceOp ?: $this->opToSlug($r->operation_desc ?? null);

        $responded = $this->hasResponded($email);
        if (in_array($wave, ['day3','day7','day14','nov','dec_mid'], true) && $responded) { $skipped++; continue; }
        if ($wave === 'thanks' && !$responded) { $skipped++; continue; }

        if (!$dry) {
            Mail::to($email)->queue(new SurveyNudgeMail(
                name: $name,
                operation: $op,
                surveyUrl: $surveyUrl,
                wave: $wave
            ));

            if ($hasMailLogs) {
                DB::table('surveys.mail_logs')->insert([
                    'campaign'   => $waveCampaign,
                    'client_id'  => $r->client_id,
                    'user_id'    => $r->user_id,
                    'email'      => $email,
                    'status'     => 'queued',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $sent++;
    }

    return ['sent' => $sent, 'skipped' => $skipped, 'total' => $rows->count()];
}



    /* ------------------------------------------
     * Exports (sin cambios funcionales relevantes)
     * ----------------------------------------*/
    public function exportWaNov(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name', 'c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $file = 'exports/wa_noviembre_' . now()->format('Ymd_His') . '.csv';
        $csv  = "name,phone,message\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = "Hola {$name} 🙌\nAntes de cerrar el año, queremos escuchar tu voz.\nSon solo 3 minutos 👉 {$surveyUrl}";
            $csv .= '"' . str_replace('"', '""', $name) . '",'
                . '"' . str_replace('"', '""', $phone) . '",'
                . '"' . str_replace('"', '""', $msg) . '"' . "\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/' . $file);
    }

    public function exportWaDecStart(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name', 'c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $file = 'exports/wa_dic_inicio_' . now()->format('Ymd_His') . '.csv';
        $csv  = "name,phone,message\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = "Estamos cerrando el 2025 ✨ Tu opinión es clave para mejorar en 2026. Responde aquí 👉 {$surveyUrl}";
            $csv .= '"' . str_replace('"', '""', $name) . '",'
                . '"' . str_replace('"', '""', $phone) . '",'
                . '"' . str_replace('"', '""', $msg) . '"' . "\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/' . $file);
    }

    public function exportCallsDecMid(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name', 'c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $script = '“Hola [Nombre], te llamo de IM Ingeniería. Estamos cerrando la encuesta de satisfacción 2025 y queremos asegurarnos de contar con tu opinión. '
            . 'Solo te tomará 3 minutos. ¿Te envío ahora mismo el link por WhatsApp o prefieres que lo diligenciemos juntos en llamada?”';

        $file = 'exports/calls_dic_mitad_' . now()->format('Ymd_His') . '.csv';
        $csv  = "name,phone,script\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = str_replace('[Nombre]', $name, $script) . " Link: {$surveyUrl}";
            $csv .= '"' . str_replace('"', '""', $name) . '",'
                . '"' . str_replace('"', '""', $phone) . '",'
                . '"' . str_replace('"', '""', $msg) . '"' . "\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/' . $file);
    }
}
