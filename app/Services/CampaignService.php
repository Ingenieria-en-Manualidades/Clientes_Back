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
    public function opToSlug(?string $txt): string
    {
        $t = Str::of((string)$txt)->lower()->squish()->toString();
        $map = [
            'maquila' => 'maquila',
            'logistica' => 'logistica', 'logística' => 'logistica',
            'manufactura' => 'manufactura',
            'aeropuertos' => 'aeropuertos',
            'zona franca' => 'zona_franca',
            'soluciones' => 'soluciones', 'soluciones especializadas' => 'soluciones',
        ];
        foreach ($map as $needle => $slug) {
            if (Str::contains($t, $needle)) return $slug;
        }
        return 'maquila';
    }

    public function hasResponded(string $email): bool
    {
        // TODO: Ajusta a tu tabla real de respuestas si aplica.
        // return DB::table('surveys.survey_responses')->where('email', $email)->exists();
        return false;
    }

    /** Día 0: envío de rebranding */
    public function sendRebranding(string $campaign, int $limit = 0, bool $dry = false): array
    {
        $hasMailLogs = Schema::hasTable('mail_logs');

        $q = DB::table('clientes as c')
            ->select([
                'c.id as client_id',
                DB::raw('NULL::int as user_id'),
                'c.nombre as client_name',
                'c.nombre as contact_name',
                'c.email as email',
                DB::raw("'maquila' as operation_desc"),
                'c.telefono as phone',
            ])
            ->whereNotNull('c.email');

        if ($hasMailLogs) {
            $q->whereNotExists(function ($s) use ($campaign) {
                $s->select(DB::raw(1))
                  ->from('mail_logs as ml')
                  ->whereColumn('ml.email', 'c.email')
                  ->where('ml.campaign', $campaign);
            });
        }

        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $sent = 0;

        foreach ($rows as $r) {
            $email = $r->email;
            if (!$email) continue;

            $name = $r->contact_name ?: $r->client_name ?: 'Cliente';
            $op   = $this->opToSlug($r->operation_desc ?? null);

            if (!$dry) {
                Mail::to($email)->queue(new RebrandingMail(
                    name: $name,
                    operation: $op,
                    surveyUrl: $surveyUrl,
                    contactPhone: $r->phone,
                    contactEmail: $email
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
            }

            $sent++;
        }

        return ['sent' => $sent, 'total' => count($rows)];
    }

    /** Nudges (día 3/7/10, nov, dec_mid, day14) */
    public function sendNudge(string $wave, string $campaign, int $limit = 0, bool $dry = false, ?string $forceOp = null): array
    {
        $hasMailLogs  = Schema::hasTable('mail_logs');
        $waveCampaign = "{$campaign}:{$wave}";

        $q = DB::table('mail_logs as ml')
            ->join('clientes as c', 'c.email', '=', 'ml.email')
            ->select([
                'c.id as client_id',
                DB::raw('NULL::int as user_id'),
                'c.nombre as client_name',
                'c.nombre as contact_name',
                'c.email as email',
                DB::raw("'maquila' as operation_desc"),
            ])
            ->where('ml.campaign', $campaign);

        if (in_array($wave, ['day3','day7','thanks'], true)) {
            $days = ['day3'=>3,'day7'=>7,'thanks'=>10][$wave];
            $targetDate = now()->subDays($days)->toDateString();
            $q->whereDate('ml.created_at', $targetDate);
        }

        if ($hasMailLogs) {
            $q->whereNotExists(function ($s) use ($waveCampaign) {
                $s->select(DB::raw(1))
                  ->from('mail_logs as ml2')
                  ->whereColumn('ml2.email', 'ml.email')
                  ->where('ml2.campaign', $waveCampaign);
            });
        }

        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $sent=0; $skipped=0;

        foreach ($rows as $r) {
            $email = $r->email; if (!$email) { $skipped++; continue; }
            $name  = $r->contact_name ?: $r->client_name ?: 'Cliente';
            $op    = $forceOp ?: $this->opToSlug($r->operation_desc ?? null);

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
                    DB::table('mail_logs')->insert([
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

        return ['sent' => $sent, 'skipped' => $skipped, 'total' => count($rows)];
    }

    /** CSV WhatsApp (Noviembre) */
    public function exportWaNov(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name','c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $file = 'exports/wa_noviembre_'.now()->format('Ymd_His').'.csv';
        $csv  = "name,phone,message\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = "Hola {$name} 🙌\nAntes de cerrar el año, queremos escuchar tu voz.\nSon solo 3 minutos 👉 {$surveyUrl}";
            $csv .= '"'.str_replace('"','""',$name).'",'
                  . '"'.str_replace('"','""',$phone).'",'
                  . '"'.str_replace('"','""',$msg).'"'."\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/'.$file);
    }

    /** CSV WhatsApp (Inicio Diciembre) */
    public function exportWaDecStart(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name','c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $file = 'exports/wa_dic_inicio_'.now()->format('Ymd_His').'.csv';
        $csv  = "name,phone,message\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = "Estamos cerrando el 2025 ✨ Tu opinión es clave para mejorar en 2026. Responde aquí 👉 {$surveyUrl}";
            $csv .= '"'.str_replace('"','""',$name).'",'
                  . '"'.str_replace('"','""',$phone).'",'
                  . '"'.str_replace('"','""',$msg).'"'."\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/'.$file);
    }

    /** CSV Llamadas (Mitad Diciembre) */
    public function exportCallsDecMid(int $limit = 0, bool $dry = false): ?string
    {
        $q = DB::table('clientes as c')
            ->select(['c.nombre as name','c.telefono as phone'])
            ->whereNotNull('c.telefono');
        if ($limit > 0) $q->limit($limit);
        $rows = $q->get();

        $surveyUrl = config('rebranding.survey_url');
        $script = '“Hola [Nombre], te llamo de IM Ingeniería. Estamos cerrando la encuesta de satisfacción 2025 y queremos asegurarnos de contar con tu opinión. '
                . 'Solo te tomará 3 minutos. ¿Te envío ahora mismo el link por WhatsApp o prefieres que lo diligenciemos juntos en llamada?”';

        $file = 'exports/calls_dic_mitad_'.now()->format('Ymd_His').'.csv';
        $csv  = "name,phone,script\n";

        foreach ($rows as $r) {
            $name  = $r->name ?: 'Cliente';
            $phone = preg_replace('/\s+/', '', (string)$r->phone);
            if (!$phone) continue;
            $msg = str_replace('[Nombre]', $name, $script) . " Link: {$surveyUrl}";
            $csv .= '"'.str_replace('"','""',$name).'",'
                  . '"'.str_replace('"','""',$phone).'",'
                  . '"'.str_replace('"','""',$msg).'"'."\n";
        }

        if ($dry) return null;

        Storage::disk('local')->put($file, $csv);
        return storage_path('app/'.$file);
    }
}
