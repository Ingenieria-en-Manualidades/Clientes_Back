<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

class MarkMailLogAsSent implements ShouldQueue
{
    public function handle(MessageSent $event): void
    {
        $symfony  = $event->message;
        $headers  = $symfony->getHeaders();
        $logId    = $headers->get('X-Log-Id')?->getBodyAsString();
        $campaign = $headers->get('X-Campaign')?->getBodyAsString();
        $toObjs   = $symfony->getTo() ?? [];
        $email    = $toObjs ? reset($toObjs)->getAddress() : null;
        if (!$email) return;

        // Preferir logId si existe
        if ($logId) {
            DB::table('surveys.mail_logs')
              ->where('id', $logId)
              ->update(['status' => 'sent', 'error' => null, 'updated_at' => now()]);
            return;
        }

        // Fallback por email/campaign al último registro
        $lastId = DB::table('surveys.mail_logs')
            ->where('email', $email)
            ->when($campaign, fn($q) => $q->where('campaign', $campaign))
            ->orderByDesc('created_at')
            ->value('id');

        if ($lastId) {
            DB::table('surveys.mail_logs')
              ->where('id', $lastId)
              ->update(['status' => 'sent', 'error' => null, 'updated_at' => now()]);
        }
    }
}
