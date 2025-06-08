<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Confirmación de Registro - Andy Corporación</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f4f4;
            color: #333;
            padding: 0;
            margin: 0;
        }

        .email-container {
            background-color: #ffffff;
            max-width: 600px;
            margin: 40px auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background-color: #0b3c87;
            padding: 30px;
            text-align: center;
            color: #fff;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .content {
            padding: 30px;
            font-size: 16px;
            line-height: 1.6;
        }

        .content p {
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            background-color: #0b3c87;
            color: #fff !important;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
        }

        .footer {
            background-color: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <h1>Bienvenido a Andy Corporación</h1>
        </div>
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            <p>Gracias por registrarte en nuestra app. Para completar tu registro y empezar a disfrutar de todos
                nuestros servicios, por favor confirma tu identidad haciendo clic en el siguiente botón:</p>

            <p style="text-align: center;">
                <a href="#" class="button">Confirmar mi cuenta</a>
            </p>

            <p>Si no creaste esta cuenta, puedes ignorar este mensaje.</p>

            <p>Gracias,<br>
                El equipo de Andy Corporación</p>
        </div>
        <div class="footer">
            © {{ date('Y') }} Andy Corporación. Todos los derechos reservados.
        </div>
    </div>
</body>

</html>