<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue; // si quieres cola, descomenta e implementa
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RebrandingMail extends Mailable /* implements ShouldQueue */
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $emailTo,
        public string $operation,   // 'logistica'|'manufactura'|'maquila'|'aeropuertos'|'zona_franca'|'soluciones'
        public string $surveyUrl,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
    ) {}

    public function build()
    {
        // Ruta base que nos diste: public/images/mail
        $base = public_path('images/mail');

        // Header principal (usa tu header.png de la carpeta)
        $headerPath = $base . DIRECTORY_SEPARATOR . 'header.png';

        // Mapear operación -> archivo de logo exacto (con espacios tal cual)
        $map = [
            'logistica'    => 'IM LOGISTICA.png',
            'manufactura'  => 'IM MANUFACTURA.png',
            'maquila'      => 'IM MAQUILA.png',
            'aeropuertos'  => 'IM AEROPUERTOS.png',
            'zona_franca'  => 'IM ZONA FRANCA.png',
            'soluciones'   => 'IM SOLUCIONES.png',
        ];
        $opKey = strtolower($this->operation);
        $opLogoPath = isset($map[$opKey]) ? $base . DIRECTORY_SEPARATOR . $map[$opKey] : null;

        // Generar CIDs solo si existe el archivo
        $headerCid = (is_file($headerPath)) ? $this->embed($headerPath) : null;
        $opLogoCid = (is_file($opLogoPath ?? '')) ? $this->embed($opLogoPath) : null;

        return $this->subject('Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería')
            ->view('emails.rebranding')
            ->with([
                'name'         => $this->name,
                'surveyUrl'    => $this->surveyUrl,
                'operation'    => $this->operation,
                'contactPhone' => $this->contactPhone,
                'contactEmail' => $this->contactEmail,
                'headerCid'    => $headerCid,
                'opLogoCid'    => $opLogoCid,
            ]);
    }
}
