<?php

// app/Listeners/MarkMailLogAsFailedFromQueue.php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarkMailLogAsFailedFromQueue implements ShouldQueue
{
    public function handle(JobFailed $event): void
    {
        if (! Str::endsWith($event->job->resolveName(), 'Illuminate\\Mail\\SendQueuedMailable')) return;

        $payload  = $event->job->payload();
        $command  = $payload['data']['command'] ?? null;

        $logId = null; $email = null; $campaign = null;

        if (is_string($command)) {
            try {
                $obj = @unserialize($command, ['allowed_classes' => true]);
                if (isset($obj->message)) {
                    $symfony  = $obj->message->getSymfonyMessage();
                    $campaign = $symfony->getHeaders()->get('X-Campaign')?->getBodyAsString();
                    $logId    = $symfony->getHeaders()->get('X-Log-Id')?->getBodyAsString();
                    $toObjs   = $symfony->getTo() ?? [];
                    $email    = $toObjs ? reset($toObjs)->getAddress() : null;
                }
            } catch (\Throwable) { /* ignore */ }
        }

        $error = (string) $event->exception?->getMessage();
        $this->updateFailed($logId, $email, $campaign, $error);
    }

    private function updateFailed(?string $logId, ?string $email, ?string $campaign, string $error): void
    {
        if ($logId) {
            DB::table('surveys.mail_logs')->where('id',$logId)
              ->update(['status'=>'failed','error'=>$error,'updated_at'=>now()]);
            return;
        }
        if (!$email) return;

        $lastId = DB::table('surveys.mail_logs')
            ->where('email',$email)
            ->when($campaign,fn($q)=>$q->where('campaign',$campaign))
            ->orderByDesc('created_at')->value('id');

        if ($lastId) {
            DB::table('surveys.mail_logs')->where('id',$lastId)
              ->update(['status'=>'failed','error'=>$error,'updated_at'=>now()]);
        }
    }
}
