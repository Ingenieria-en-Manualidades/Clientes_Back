@php
$safeName = $name ?? 'Cliente';
$op = strtolower($operation ?? '');
$publicBase = asset('images/mail');

$opMessage = match ($op) {
'logistica' => 'Como parte de nuestra historia compartida, ahora tu relación con nosotros se vivirá bajo el nombre IM Logística. Un nombre que refleja lo que ya hemos construido juntos en este camino.',
'manufactura' => 'Tú ya eres parte de IM Manufactura, y con este nombre reafirmamos la identidad de lo que hemos venido logrando juntos en cada proyecto.',
'maquila' => 'Con nosotros formas parte de IM Maquila, un espacio que evoluciona en imagen, pero mantiene intacto lo que hemos construido contigo.',
'aeropuertos' => 'Tu confianza nos ha permitido crecer, y ahora esa relación se fortalece bajo el nombre IM Aeropuertos, la misma esencia, un nombre más claro.',
'zona_franca' => 'Contigo caminamos como IM Zona Franca, un sello que evoluciona en imagen, pero que sigue siendo el mismo equipo a tu lado.',
'soluciones' => 'Desde ahora eres parte de IM Soluciones, un reflejo más fiel de lo que hemos trabajado juntos y seguiremos construyendo.',
default => null,
};

$opMap = [
'logistica' => 'IM LOGISTICA.png',
'manufactura' => 'IM MANUFACTURA.png',
'maquila' => 'IM MAQUILA.png',
'aeropuertos' => 'IM AEROPUERTOS.png',
'zona_franca' => 'IM ZONA FRANCA.png',
'soluciones' => 'IM SOLUCIONES.png',
];
$opPublicFile = $opMap[$op] ?? null;
@endphp

<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta name="x-apple-disable-message-reformatting">
  <title>IM Ingeniería – Encuesta de satisfacción</title>
  <style>
    .btn:hover {
      opacity: .9 !important;
    }

    @media (max-width: 620px) {
      .container {
        width: 100% !important;
      }

      .px {
        padding-left: 16px !important;
        padding-right: 16px !important;
      }

      .header-img {
        width: 100% !important;
        height: auto !important;
      }

      .op-logo {
        width: 180px !important;
        height: auto !important;
      }
    }
  </style>
</head>

<body style="margin:0;padding:0;background:#f6f7fb;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería</div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table class="container" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:100%;background:#fff;border-radius:12px;overflow:hidden;">
          <tr>
            <td>
              @if(!empty($headerPath) && is_file($headerPath))
              <img src="{{ $message->embed($headerPath) }}" alt="IM Ingeniería" class="header-img" style="display:block;width:600px;max-width:100%;height:auto;">
              @else
              <img src="{{ $publicBase }}/header.png" alt="IM Ingeniería" class="header-img" style="display:block;width:600px;max-width:100%;height:auto;">
              @endif
            </td>
          </tr>

          <tr>
            <td class="px" style="padding:24px 32px 8px;">
              <h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:22px;color:#0b3677;">
                Ayúdanos a mejorar juntos: Encuesta de satisfacción IM Ingeniería
              </h1>
            </td>
          </tr>

          <tr>
            <td class="px" style="padding:0 32px 24px;">
              <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                Hola <strong>{{ $safeName }}</strong>,
              </p>

              <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                En IM llevamos más de 38 años caminando junto a nuestros clientes y equipos de trabajo, creciendo, aprendiendo y transformándonos con ellos. Hoy queremos compartir contigo una evolución importante: dejamos atrás el nombre <em>Ingeniería en Manualidades y Logística</em> para convertirnos en <strong>IM Ingeniería</strong>. Es una marca más cercana, moderna y coherente con lo que somos hoy, pero con la misma esencia de siempre.
              </p>


            <td valign="top" style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#0b2a66;line-height:1.6;">
              @if($opMessage)
              <p style="margin:0;">{{ $opMessage }}</p>
              @endif


              <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                <strong>Lo que cambia:</strong> nuestro logo y nuestra forma de presentarnos.<br>
                <strong>Lo que no cambia:</strong> tu equipo de contacto, la calidad de nuestros servicios y el compromiso que nos une.
              </p>

              <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                Queremos seguir mejorando juntos. Por eso, te invitamos a responder nuestra <strong>encuesta de satisfacción</strong>:
              </p>

              <p style="margin:0 0 24px;">
                <a href="{{ $surveyUrl }}" target="_blank" rel="noopener"
                  style="display:inline-block;background:#2f80ed;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;">
                  Responder encuesta
                </a>
              </p>

              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                Gracias por ser parte de IM Ingeniería. Seguiremos construyendo soluciones ingeniosas y sostenibles, juntos.
              </p>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:18px;background:#f5f7fb;border-top:1px solid #edf0f6;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">
                <strong>IM Ingeniería</strong><br />
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