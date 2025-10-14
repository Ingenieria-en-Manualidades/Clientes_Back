<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ThankYouSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function build()
    {
        $base = public_path('images/mail');

        $headerPath = $base . DIRECTORY_SEPARATOR . 'header22.png';


        return $this->subject('¡Gracias por tu confianza y tu tiempo!')
            ->view('emails.thank_you_survey')
            ->with([
                'name'       => $this->name,
                'headerPath' => $headerPath,
            ]);
    }
}
