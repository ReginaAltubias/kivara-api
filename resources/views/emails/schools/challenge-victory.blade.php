<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vitória no Desafio KIVARA</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #176c58; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 30px 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
        .reward { background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Parabéns, {{ $schoolName }}!</h1>
    </div>
    <div class="content">
        <p>A sua escola venceu o Desafio <strong>{{ $challengeName }}</strong> na plataforma KIVARA!</p>
        
        <p>Graças ao excelente desempenho e engajamento dos seus alunos e professores, o vosso prémio institucional foi desbloqueado:</p>
        
        <div class="reward">
            <strong>Recompensa:</strong><br>
            {{ $rewardDescription }}
        </div>
        
        <p>A nossa equipa entrará em contacto nas próximas 48 horas para coordenar a entrega do prémio.</p>
        
        <p>Obrigado por apoiar a literacia financeira!<br>A equipa KIVARA</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} KIVARA EdTech. Todos os direitos reservados.
    </div>
</body>
</html>
