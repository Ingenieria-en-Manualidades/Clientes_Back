<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reestablecer contraseña</title>
    <style>
        p {
            font-size: 18px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .content-img {
            padding: 5px;
            background-color: white;
            border-left: 1px solid black;
            border-top: 1px solid black;
            border-bottom: 1px solid black;
        }

        .content-title {
            width: 100%;
            color: white;
            font: bold;
            font-size: 20px;
            background-color: #0063a6;
            border: 1px solid #0063a6;
            text-align: left;
            padding-left: 15px;
            padding-top: 1.5%;
        }

        .button-link {
            background-color: white;
            border: 2px solid #007cbb;
            border-radius: 10px;
            color: #007cbb;
            padding: 7px 20px;
            font-size: 18px;
            font: bold;
        }

        .button-link:hover {
            cursor: pointer;
        }

        .content-email {
            width: 95.4%;
            padding: 18px;
            border-left: 1px solid black;
            border-right: 1px solid black;
            border-bottom: 1px solid black;
        }
    </style>
</head>

<body>
    <div>
        <div style="display: inline-flex; width: 100%">
            <div class="content-img">
                <img src="cid:logo.png" alt="Logo IM" width="125px">
            </div>
            <div class="content-title">
                <p style="font-size: 30px">Actualizar contraseña</p>
            </div>
        </div>
        <div class="content-email">
            <p>Hola usuario con el correo: <b>{{ $email }}</b>.</p>
            <p>¿Has solicitado reestablecer tu contraseña?</p>
            <p>Haz clic en el siguente botón para seguir con el proceso:</p>
            <a href="{{ $link }}" target="_blank">
                <button class="button-link" type="button">
                    Reestablecer contraseña
                </button>
            </a>
            <p>Si no lo solicitaste este cambio de contraseña solo ignora este mensaje.</p>
        </div>
    </div>
</body>

</html>
