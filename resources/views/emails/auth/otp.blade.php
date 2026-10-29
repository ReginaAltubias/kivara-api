<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ativação de conta — KIVARA</title>
</head>
<body style="font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; background:#f3f4f6; margin:0; padding:32px; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px; font-size:22px; color:#122f54;">Bem-vindo(a) ao KIVARA</h1>
        <p>Olá, {{ $user->name }}.</p>
        <p>Para ativar a sua conta, introduza este código de 6 dígitos. Expira em 24 horas.</p>
        <p style="font-size:32px; font-weight:800; letter-spacing:8px; text-align:center; background:#f0fdf4; padding:16px; border-radius:8px; color:#187558;">{{ $activationCode }}</p>
        <p><a href="{{ $activationUrl }}" style="color:#187558;">Abrir a página de ativação</a></p>
        <p style="font-size:12px; color:#6b7280;">Se não criou esta conta, ignore este email.</p>
    </div>
</body>
</html>
