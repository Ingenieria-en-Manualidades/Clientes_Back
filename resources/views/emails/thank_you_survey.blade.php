@php
$safeName = $name ?? 'Cliente';
$op = strtolower($operation ?? '');
$publicBase = asset('images/mail');
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

<body>

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
                                Gracias por responder nuestra encuesta de satisfacción 💙. </p>

                            <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                                Tu voz nos inspira a seguir mejorando día a día y a crear soluciones más ingeniosas y sostenibles.
                            </p>

                            <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                                En IM Ingeniería creemos que avanzar solo es posible si lo hacemos <strong>juntos</strong>.
                            </p>

                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b2b2b;">
                                ¡Seguiremos trabajando para ti! </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:18px;background:#f5f7fb;border-top:1px solid #edf0f6;">
                            <img src="{{ $publicBase }}/Firma-Correo-Outlook.png" alt="IM Ingeniería" class="header-img" style="display:block;width:600px;max-width:100%;height:auto;">
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>