{{-- resources/views/emails/rebranding.blade.php --}}
@php
  // Variables esperadas:
  // $name, $operation, $surveyUrl, $contactPhone?, $contactEmail?,
  // $headerPath (ruta absoluta a public/images/mail/img/header.png),
  // $opLogoPath (ruta absoluta al logo de la operación)
  $safeName = $name ?? 'Cliente';
  $op = strtolower($operation ?? '');

  // Texto por operación
  $opMessage = match ($op) {
      'logistica'   => 'Como parte de nuestra historia compartida, ahora tu relación con nosotros se vivirá bajo el nombre IM Logística. Un nombre que refleja lo que ya hemos construido juntos en este camino.',
      'manufactura' => 'Tú ya eres parte de IM Manufactura, y con este nombre reafirmamos la identidad de lo que hemos venido logrando juntos en cada proyecto.',
      'maquila'     => 'Con nosotros formas parte de IM Maquila, un espacio que evoluciona en imagen, pero mantiene intacto lo que hemos construido contigo.',
      'aeropuertos' => 'Tu confianza nos ha permitido crecer, y ahora esa relación se fortalece bajo el nombre IM Aeropuertos, la misma esencia, un nombre más claro.',
      'zona_franca' => 'Contigo caminamos como IM Zona Franca, un sello que evoluciona en imagen, pero que sigue siendo el mismo equipo a tu lado.',
      'soluciones'  => 'Desde ahora eres parte de IM Soluciones, un reflejo más fiel de lo que hemos trabajado juntos y seguiremos construyendo.',
      default       => null,
  };

  // Para fallback por URL pública si el embed falla
  $publicBase = asset('images/mail/img');
  $opMap = [
      'logistica'   => 'IM LOGISTICA.png',
      'manufactura' => 'IM MANUFACTURA.png',
      'maquila'     => 'IM MAQUILA.png',
      'aeropuertos' => 'IM AEROPUERTOS.png',
      'zona_franca' => 'IM ZONA FRANCA.png',
      'soluciones'  => 'IM SOLUCIONES.png',
  ];
  $opPublicFile = $opMap[$op] ?? null;
@endphp

<!doctype html>
<html lang="es" style="margin:0;padding:0;">
<head>
  <meta charset="utf-8" />
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>IM Ingeniería – Encuesta de satisfacción</title>
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
  <!-- Preheader (oculto) -->
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table class="container" role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
          {{-- Header con imagen embebida (CID) y fallback por URL pública --}}
          <tr>
            <td>
              @if(!empty($headerPath) && is_file($headerPath))
                <img src="{{ $message->embed($headerPath) }}" alt="IM Ingeniería" class="header-img"
                     style="display:block;width:600px;max-width:100%;height:auto;border:0;outline:none;">
              @else
                <img src="{{ $publicBase }}/header.png" alt="IM Ingeniería" class="header-img"
                     style="display:block;width:600px;max-width:100%;height:auto;border:0;outline:none;">
              @endif
            </td>
          </tr>

          {{-- Título --}}
          <tr>
            <td class="px" style="padding:24px 32px 8px 32px;">
              <h1 style="margin:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:1.35;color:#0b3677;">
                Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería
              </h1>
            </td>
          </tr>

          {{-- Cuerpo --}}
          <tr>
            <td class="px" style="padding:0 32px 24px 32px;">
              <p style="margin:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
                Hola <strong>{{ $safeName }}</strong>,
              </p>

              <p style="margin:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
                En IM llevamos más de 38 años caminando junto a nuestros clientes y equipos de trabajo, creciendo, aprendiendo y transformándonos con ellos.
                Hoy queremos compartir contigo una evolución importante: dejamos atrás el nombre <em>Ingeniería en Manualidades y Logística</em> para convertirnos en <strong>IM Ingeniería</strong>.
                Es una marca más cercana, moderna y coherente con lo que somos hoy, pero con la misma esencia de siempre.
              </p>

              @if($opMessage || !empty($opLogoPath))
              <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;background:#f3f7ff;border-radius:8px;">
                <tr>
                  <td style="padding:14px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">
                      <tr>
                        @if(!empty($opLogoPath) && is_file($opLogoPath))
                          <td width="190" valign="top" style="padding-right:12px;">
                            <img src="{{ $message->embed($opLogoPath) }}" alt="IM {{ ucfirst(str_replace('_',' ',$op)) }}" class="op-logo"
                                 style="display:block;width:190px;max-width:100%;height:auto;border:0;">
                          </td>
                        @elseif($opPublicFile)
                          <td width="190" valign="top" style="padding-right:12px;">
                            <img src="{{ $publicBase }}/{{ $opPublicFile }}" alt="IM {{ ucfirst(str_replace('_',' ',$op)) }}" class="op-logo"
                                 style="display:block;width:190px;max-width:100%;height:auto;border:0;">
                          </td>
                        @endif
                        <td valign="top">
                          @if($opMessage)
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#0b2a66;">
                              {{ $opMessage }}
                            </p>
                          @endif
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
              @endif

              <p style="margin:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
                <strong>Lo que cambia:</strong> nuestro logo y nuestra forma de presentarnos.<br>
                <strong>Lo que no cambia:</strong> tu equipo de contacto, la calidad de nuestros servicios y el compromiso que nos une.
              </p>

              <p style="margin:0 0 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
                Queremos seguir mejorando juntos. Por eso, te invitamos a responder nuestra <strong>encuesta de satisfacción</strong>:
              </p>

              <p style="margin:0 0 24px 0;">
                <a href="{{ $surveyUrl }}" target="_blank" rel="noopener"
                   style="display:inline-block;background:#2f80ed;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;">
                  Responder encuesta
                </a>
              </p>

              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
                Gracias por ser parte de IM Ingeniería. Seguiremos construyendo soluciones ingeniosas y sostenibles, juntos.
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td align="center" style="padding:18px;background:#f5f7fb;border-top:1px solid #edf0f6;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#6b7280;">
                <strong>IM Ingeniería</strong><br/>
                @if(!empty($contactPhone) || !empty($contactEmail))
                  {{ $contactPhone ?? '' }}{{ (!empty($contactPhone) && !empty($contactEmail)) ? ' | ' : '' }}{{ $contactEmail ?? '' }}
                @endif
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
