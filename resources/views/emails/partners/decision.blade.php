<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Candidatura de parceiro — KIVARA</title>
</head>
<body style="font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; background:#f3f4f6; margin:0; padding:32px; color:#1f2937;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; border:1px solid #e5e7eb;">
        @if ($approved)
            <h1 style="margin:0 0 16px; font-size:22px; color:#187558;">Candidatura aprovada</h1>
            <p>Olá, {{ $partner->director_name ?? $partner->name }}.</p>
            <p>A candidatura de <strong>{{ $partner->name }}</strong> foi aprovada. Já pode entrar, carregar a carteira e criar programas e desafios.</p>
            <p style="text-align:center;"><a href="{{ $loginUrl }}" style="display:inline-block; background:#187558; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:700;">Entrar na KIVARA</a></p>
        @else
            <h1 style="margin:0 0 16px; font-size:22px; color:#122f54;">Candidatura não aprovada</h1>
            <p>Olá, {{ $partner->director_name ?? $partner->name }}.</p>
            <p>Lamentamos, mas a candidatura de <strong>{{ $partner->name }}</strong> não foi aprovada nesta altura.</p>
            @if ($partner->rejection_reason)
                <p style="background:#fef3c7; padding:12px; border-radius:8px;">Motivo: {{ $partner->rejection_reason }}</p>
            @endif
            <p>Pode contactar a equipa KIVARA para mais informações.</p>
        @endif
    </div>
</body>
</html>
