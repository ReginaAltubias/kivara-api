<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bem-vindo ao KIVARA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f5;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #4F46E5; /* KIVARA primary color */
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            background-color: #4F46E5;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        .credentials-box {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            font-size: 16px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>KIVARA</h2>
        </div>
        <div class="content">
            <p>Bem-vindo(a)!</p>
            <p>Foste convidado(a) para te juntares à equipa da escola <strong>{{ $schoolName }}</strong> no KIVARA.</p>
            
            <p>A tua conta já foi criada com sucesso. Aqui estão as tuas credenciais de acesso provisórias. No teu primeiro login, o sistema irá solicitar que definas uma nova palavra-passe por motivos de segurança.</p>

            <div class="credentials-box">
                <p><strong>Email:</strong> {{ $loginEmail }}</p>
                <p><strong>Password:</strong> {{ $generatedPassword }}</p>
            </div>
            
            <div class="button-container">
                <a href="https://kivara.app/login" class="button">Fazer Login Agora</a>
            </div>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
            <p>Se tiveres dúvidas ou precisares de suporte, não hesites em contactar a nossa equipa.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} KIVARA EdTech. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
