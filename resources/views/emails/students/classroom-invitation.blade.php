<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convite para Turma - KIVARA</title>
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
            background-color: #4F46E5; /* KIVARA primary color example */
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
        .invite-code-box {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            font-size: 18px;
            letter-spacing: 2px;
            font-weight: bold;
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
            <p>Olá!</p>
            <p>O professor <strong>{{ $teacherName }}</strong> convidou-te para te juntares à turma <strong>{{ $classroomName }}</strong> da escola <strong>{{ $schoolName }}</strong> no KIVARA.</p>
            
            <div class="button-container">
                <a href="https://kivara.app/register?invite_code={{ $inviteCode }}&role=teen" class="button">Criar a minha Conta</a>
            </div>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
            
            <p style="text-align: center;">Se já descarregaste a app, usa o código abaixo no teu perfil para entrares na turma:</p>
            <div class="invite-code-box">
                {{ $inviteCode }}
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} KIVARA EdTech. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
