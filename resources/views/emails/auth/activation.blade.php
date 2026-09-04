<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ativação de Conta — KIVARA</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #1f2937;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #e5e7eb;
        }
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 32px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
        }
        .welcome-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-top: 0;
            margin-bottom: 12px;
        }
        .credentials-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #4f46e5;
            padding: 18px 20px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .credentials-item {
            margin: 8px 0;
            font-size: 15px;
        }
        .credentials-label {
            font-weight: 600;
            color: #64748b;
        }
        .credentials-value {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            color: #0f172a;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .otp-section {
            text-align: center;
            margin: 32px 0;
            padding: 20px;
            background: #fdf4ff;
            border-radius: 12px;
            border: 1px dashed #d946ef;
        }
        .otp-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: #a21caf;
            margin-bottom: 8px;
        }
        .otp-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 38px;
            font-weight: 900;
            letter-spacing: 8px;
            color: #4f46e5;
            margin: 10px 0;
        }
        .otp-expiry {
            font-size: 12px;
            color: #6b7280;
        }
        .button-container {
            text-align: center;
            margin: 32px 0 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }
        .footer {
            background-color: #f9fafb;
            border-top: 1px solid #f3f4f6;
            padding: 24px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header com Logótipo KIVARA -->
            <div class="header">
                <h1>🦁 KIVARA</h1>
                <p>Educação e Gestão Financeira Inteligente</p>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content">
                <h2 class="welcome-title">Bem-vindo(a) ao KIVARA!</h2>
                <p>Olá, <strong>{{ $user->name }}</strong>. A sua conta institucional foi criada com sucesso na plataforma KIVARA.</p>
                <p>Para aceder à sua conta e começar a gerir as suas atividades, utilize as credenciais temporárias abaixo e conclua a ativação através do código OTP.</p>

                <!-- Bloco de Credenciais Temporárias -->
                <div class="credentials-box">
                    <div class="credentials-item">
                        <span class="credentials-label">E-mail:</span>
                        <strong style="color: #1e293b;">{{ $user->email }}</strong>
                    </div>
                    <div class="credentials-item">
                        <span class="credentials-label">Palavra-passe Temporária:</span>
                        <span class="credentials-value">{{ $temporaryPassword }}</span>
                    </div>
                </div>

                <!-- Código de Ativação em Grande Destaque -->
                <div class="otp-section">
                    <div class="otp-label">Código de Ativação (OTP)</div>
                    <div class="otp-code">{{ $activationCode }}</div>
                    <div class="otp-expiry">⏱️ Este código expira em 24 horas.</div>
                </div>

                <!-- Call to Action -->
                <div class="button-container">
                    <a href="{{ $activationUrl }}" class="btn-primary" target="_blank">Ativar Minha Conta</a>
                </div>
                
                <p style="font-size: 13px; color: #6b7280; text-align: center; margin-top: 16px;">
                    Ao iniciar sessão pela primeira vez, ser-lhe-á solicitado que defina uma nova palavra-passe definitiva.
                </p>
            </div>

            <!-- Rodapé -->
            <div class="footer">
                <p>© {{ date('Y') }} KIVARA Technologies. Todos os direitos reservados.</p>
                <p>Se não solicitou a criação desta conta, por favor ignore esta mensagem.</p>
            </div>
        </div>
    </div>
</body>
</html>
