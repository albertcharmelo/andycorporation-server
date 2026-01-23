<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Código de Recuperación - Andy Corporación</title>
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

        .otp-code {
            background-color: #f4f4f4;
            border: 2px solid #0b3c87;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #0b3c87;
            margin: 30px 0;
            font-family: 'Courier New', monospace;
        }

        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .warning p {
            margin: 0;
            font-size: 14px;
            color: #856404;
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
            <h1>Recuperación de Contraseña</h1>
        </div>
        <div class="content">
            <p>Hola {{ $userName }},</p>
            <p>Hemos recibido una solicitud para restablecer tu contraseña. Utiliza el siguiente código de verificación:</p>

            <div class="otp-code">
                {{ $otpCode }}
            </div>

            <div class="warning">
                <p><strong>Importante:</strong> Este código expirará en 15 minutos. Si no solicitaste este cambio, puedes ignorar este mensaje.</p>
            </div>

            <p>Si no solicitaste este cambio, puedes ignorar este mensaje de forma segura.</p>

            <p>Gracias,<br>
                El equipo de Andy Corporación</p>
        </div>
        <div class="footer">
            © {{ date('Y') }} Andy Corporación. Todos los derechos reservados.
        </div>
    </div>
</body>

</html>
