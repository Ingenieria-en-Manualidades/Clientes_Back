<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyNudgeMail extends Mailable /* implements ShouldQueue */
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $operation,      // 'logistica'|'manufactura'|'maquila'|'aeropuertos'|'zona_franca'|'soluciones'
        public string $surveyUrl,
        public string $wave,   
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public ?string $username = null,    
  public ?string $tempPassword = null         // 'day3'|'day7'|'thanks'|'day14'|'nov'|'dec_mid'
    ) {}

    public function build()
    {
        $base = public_path('images/mail');
        $headerPath = $base . DIRECTORY_SEPARATOR . 'header22.png';

        $map = [
             'Logistica'    => 'IM LOGISTICA.png',
            'Manufactura'  => 'IM MANUFACTURA.png',
            'Maquila'      => 'IM MAQUILA.png',
            'Aeropuertos'  => 'IM AEROPUERTOS.png',
            'zona_franca' => 'IM ZONA FRANCA.png',
            'soluciones'  => 'IM SOLUCIONES.png',
        ];
        $opKey = strtolower($this->operation);
        $opLogoPath = isset($map[$opKey]) ? $base . DIRECTORY_SEPARATOR . $map[$opKey] : null;

        $subject = match ($this->wave) {
            'day3'   => '¿Nos ayudas con tu opinión? 🙏 Encuesta de satisfacción IM Ingeniería',
            'day7'   => 'Tu voz hace la diferencia: Encuesta de satisfacción',
            'thanks' => '¡Gracias por tu confianza y tu tiempo!',
            'day14'  => 'Aún puedes compartir tu opinión ✨',
            'nov'    => 'Tu opinión nos ayudará a empezar el 2026 más fuertes 🚀',
            'dec_mid'=> 'Tu opinión puede marcar la diferencia ✨',
            default  => 'Encuesta de satisfacción IM Ingeniería',
        };

        return $this->subject($subject)
            ->view('emails.survey_nudge')
            ->with([
                'name'       => $this->name,
                'operation'  => $this->operation,
                'surveyUrl'  => $this->surveyUrl,
                'wave'       => $this->wave,
                'headerPath' => $headerPath,
                'opLogoPath' => $opLogoPath,
            ]);
    }
}
