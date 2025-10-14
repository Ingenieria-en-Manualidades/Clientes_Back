<?php


// app/Listeners/MarkMailLogAsFailedFromQueue.php
namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarkMailLogAsFailedFromQueue
{
    public function handle(JobFailed $event): void
    {
        // Solo correos (job del mailable encolado)
        if (! Str::endsWith($event->job->resolveName(), 'Illuminate\\Mail\\SendQueuedMailable')) {
            return;
        }

        // Intentar extraer el destinatario/campaña del payload serializado
        $payload = $event->job->payload();
        $command = $payload['data']['command'] ?? null;
        $email   = null;
        $campaign = null;

        if (is_string($command)) {
            try {
                $obj = @unserialize($command, ['allowed_classes' => true]);
                // $obj es Illuminate\Mail\SendQueuedMailable
                // Extraer destinatarios y mailable interno
                if (isset($obj->message)) {
                    $symfony = $obj->message->getSymfonyMessage();
                    $to = $symfony->getTo();
                    $email = $to ? array_key_first($to) : null;
                    $campaign = $symfony->getHeaders()->getHeaderBody('X-Campaign');
                }
            } catch (\Throwable $e) {
                // ignora
            }
        }

        // Fallback: si no logramos leer email del payload, no podemos mapear con precisión
        if (! $email) return;

        $q = DB::table('mail_logs')->where('email', $email);
        if ($campaign) $q->where('campaign', $campaign);

        $q->orderByDesc('id')->limit(1)->update([
            'status'     => 'failed',
            'error'      => (string) $event->exception?->getMessage(),
            'updated_at' => now(),
        ]);
    }
}
