<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RebrandingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $operation, // logistica|manufactura|maquila|aeropuertos|zona_franca|soluciones|generico
        public string $surveyUrl
    ) {}

    public function build()
    {
        return $this->from(config('rebranding.from_email'), config('rebranding.from_name'))
            ->subject(config('rebranding.subject'))
            ->markdown('emails.rebranding', [
                'name'      => $this->name,
                'operation' => $this->operation,
                'surveyUrl' => $this->surveyUrl,
            ]);
    }
}
