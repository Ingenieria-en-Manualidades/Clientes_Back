<?php

// app/Listeners/MarkMailLogAsSent.php
namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

class MarkMailLogAsSent
{
    public function handle(MessageSent $event): void
    {
        $symfony = $event->message;
        $to = $symfony->getTo();
        $email = $to ? array_key_first($to) : null;

        // Si añadiste cabecera X-Campaign en el mailable, úsala
        $campaign = $symfony->getHeaders()->getHeaderBody('X-Campaign');

        if (!$email) return;

        $q = DB::table('mail_logs')->where('email', $email);
        if ($campaign) $q->where('campaign', $campaign);

        $q->orderByDesc('id')->limit(1)->update([
            'status'     => 'sent',
            'updated_at' => now(),
            'error'      => null,
        ]);
    }
}
