<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperação de palavra-passe — KIVARA</title>
</head>
<body style="font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; background:#f3f4f6; margin:0; padding:32px; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px; font-size:22px; color:#122f54;">Recuperar palavra-passe</h1>
        <p>Olá, {{ $user->name }}.</p>
        <p>Recebemos um pedido para repor a sua palavra-passe. O link expira em 60 minutos.</p>
        <p style="text-align:center;"><a href="{{ $resetUrl }}" style="display:inline-block; background:#187558; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:700;">Repor palavra-passe</a></p>
        <p style="font-size:12px; color:#6b7280;">Se não fez este pedido, ignore este email: a sua palavra-passe não será alterada.</p>
    </div>
</body>
</html>
