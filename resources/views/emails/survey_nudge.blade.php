@php
  $safeName  = $name ?? 'Cliente';
  $op        = strtolower($operation ?? '');
  $publicBase= asset('images/mail');

  $opMap = [
    'logistica'   => 'IM LOGISTICA.png',
    'manufactura' => 'IM MANUFACTURA.png',
    'maquila'     => 'IM MAQUILA.png',
    'aeropuertos' => 'IM AEROPUERTOS.png',
    'zona_franca' => 'IM ZONA FRANCA.png',
    'soluciones'  => 'IM SOLUCIONES.png',
  ];
  $opPublicFile = $opMap[$op] ?? null;

  $subject = match ($wave) {
    'day3'   => '¿Nos ayudas con tu opinión? 🙏 Encuesta de satisfacción IM Ingeniería',
    'day7'   => 'Tu voz hace la diferencia: Encuesta de satisfacción',
    'thanks' => '¡Gracias por tu confianza y tu tiempo!',
    'day14'  => 'Aún puedes compartir tu opinión ✨',
    'nov'    => 'Tu opinión nos ayudará a empezar el 2026 más fuertes 🚀',
    'dec_mid'=> 'Tu opinión puede marcar la diferencia ✨',
    default  => 'Encuesta de satisfacción IM Ingeniería',
  };

  $mainBlock = match ($wave) {
    'day3' => [
      "Hola {$safeName},",
      "Queremos escucharte 🙌. Tu opinión es fundamental para seguir mejorando nuestros servicios.",
      "Responder la encuesta no te tomará más de 3 minutos y nos ayudará a crecer juntos.",
      true,
    ],
    'day7' => [
      "Hola {$safeName},",
      "Sabemos que tu tiempo es valioso ⏳, pero tu opinión lo es aún más.",
      "Aún puedes responder nuestra encuesta y ayudarnos a construir un mejor camino juntos.",
      true,
    ],
    'thanks' => [
      "Hola {$safeName},",
      "Gracias por responder nuestra encuesta de satisfacción 💙.",
      "Tu voz nos inspira a seguir mejorando día a día y a crear soluciones más ingeniosas y sostenibles. En IM Ingeniería creemos que avanzar solo es posible si lo hacemos juntos.",
      false,
    ],
    'day14' => [
      "Hola {$safeName},",
      "Estamos en los últimos días para nuestra encuesta de satisfacción 2025.",
      "Tu opinión nos permitirá seguir mejorando y creciendo juntos en el próximo año.",
      true,
    ],
    'nov' => [
      "Hola {$safeName},",
      "Se acerca el cierre del año y queremos aprovechar este momento para escucharte.",
      "Responder nuestra encuesta de satisfacción no te tomará más de 3 minutos y nos ayudará a mejorar para seguir creciendo juntos en el 2026.",
      true,
    ],
    'dec_mid' => [
      "Hola {$safeName},",
      "Este es el último envío de nuestra encuesta de satisfacción 2025.",
      "Tu opinión nos permitirá cerrar el año con aprendizajes y empezar el 2026 con más fuerza.",
      true,
    ],
    default => [
      "Hola {$safeName},",
      "Nos gustaría conocer tu opinión para mejorar continuamente.",
      "¿Nos ayudas respondiendo esta breve encuesta?",
      true,
    ],
  };
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="x-apple-disable-message-reformatting">
  <title>{{ $subject }}</title>
  <style>
    .btn:hover { opacity:.92 !important; }
    @media (max-width: 620px) {
      .container { width: 100% !important; }
      .px { padding-left:16px !important; padding-right:16px !important; }
      .header-img { width: 100% !important; height: auto !important; }
      .op-logo { width: 180px !important; height: auto !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $subject }}</div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table class="container" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:100%;background:#fff;border-radius:12px;overflow:hidden;">
          <tr>
            <td>
              @if(!empty($headerPath) && is_file($headerPath))
                <img src="{{ $message->embed($headerPath) }}" alt="IM Ingeniería" class="header-img"
                     style="display:block;width:600px;max-width:100%;height:auto;">
              @else
                <img src="{{ $publicBase }}/header.png" alt="IM Ingeniería" class="header-img"
                     style="display:block;width:600px;max-width:100%;height:auto;">
              @endif
            </td>
          </tr>

          <tr>
            <td class="px" style="padding:24px 32px 8px;">
              <h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:22px;color:#0b3677;">
                {{ $subject }}
              </h1>
            </td>
          </tr>

          <tr>
            <td class="px" style="padding:0 32px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 16px;background:#f3f7ff;border-radius:8px;">
                <tr>
                  <td style="padding:14px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        @if(!empty($opLogoPath) && is_file($opLogoPath))
                          <td width="190" valign="top" style="padding-right:12px;">
                            <img src="{{ $message->embed($opLogoPath) }}" alt="IM {{ ucfirst(str_replace('_',' ',$op)) }}" class="op-logo"
                                 style="display:block;width:190px;max-width:100%;height:auto;">
                          </td>
                        @elseif($opPublicFile)
                          <td width="190" valign="top" style="padding-right:12px;">
                            <img src="{{ $publicBase }}/{{ $opPublicFile }}" alt="IM {{ ucfirst(str_replace('_',' ',$op)) }}" class="op-logo"
                                 style="display:block;width:190px;max-width:100%;height:auto;">
                          </td>
                        @endif
                        <td valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0b2a66;line-height:1.6;">
                          <p style="margin:0 0 8px;">{{ $mainBlock[0] }}</p>
                          <p style="margin:0 0 8px;">{{ $mainBlock[1] }}</p>
                          <p style="margin:0">{{ $mainBlock[2] }}</p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              @if($mainBlock[3] ?? false)
                <p style="margin:0 0 24px;">
                  <a href="{{ $surveyUrl }}" target="_blank" rel="noopener"
                     style="display:inline-block;background:#FACC15;color:#111827;text-decoration:none;
                            padding:12px 20px;border-radius:8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;
                            border:1px solid #EAB308;">
                    Responder encuesta
                  </a>
                </p>
              @endif

              @if($wave === 'thanks')
                <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                  ¡Seguiremos trabajando para ti!
                </p>
              @else
                <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                  Gracias por acompañarnos en este camino.
                </p>
              @endif
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:18px;background:#f5f7fb;border-top:1px solid #edf0f6;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">
                <strong>IM Ingeniería</strong>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
