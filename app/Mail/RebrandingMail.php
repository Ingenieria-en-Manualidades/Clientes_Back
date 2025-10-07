<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue; // si usas colas, descomenta
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RebrandingMail extends Mailable /* implements ShouldQueue */
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $operation,   // 'logistica'|'manufactura'|'maquila'|'aeropuertos'|'zona_franca'|'soluciones'
        public string $surveyUrl,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
        public ?string $username = null,    
  public ?string $tempPassword = null
    ) {}

    public function build()
    {
        // Carpeta real de imágenes (tú confirmaste que es "public/images/mail")
        $base = public_path('images/mail');

        $headerPath = $base . DIRECTORY_SEPARATOR . 'header.png';

        $map = [
            'logistica'    => 'IM LOGISTICA.png',
            'manufactura'  => 'IM MANUFACTURA.png',
            'maquila'      => 'IM MAQUILA.png',
            'aeropuertos'  => 'IM AEROPUERTOS.png',
            'zona_franca'  => 'IM ZONA FRANCA.png',
            'soluciones'   => 'IM SOLUCIONES.png',
        ];

        $opKey      = strtolower($this->operation);
        $opLogoFile = $map[$opKey] ?? null;
        $opLogoPath = $opLogoFile ? $base . DIRECTORY_SEPARATOR . $opLogoFile : null;

        return $this->subject('Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería')
            ->view('emails.rebranding')
            ->with([
                'name'         => $this->name,
                'operation'    => $this->operation,
                'surveyUrl'    => $this->surveyUrl,
                'contactPhone' => $this->contactPhone,
                'contactEmail' => $this->contactEmail,
                'headerPath'   => $headerPath,
                'opLogoPath'   => $opLogoPath,
            ]);
    }
}
