<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida a assinatura HMAC-SHA256 dos webhooks de pagamento.
 *
 * O gateway envia o cabecalho `X-Kivara-Signature` com `hex(hmac_sha256(corpo_bruto, segredo))`
 * (prefixo `sha256=` opcional). Sem segredo configurado o webhook e recusado (503), para nunca
 * ficar aberto por esquecimento de configuracao.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.payments.webhook_secret');

        if ($secret === '') {
            abort(503, 'Webhook de pagamentos não configurado.');
        }

        $provided = (string) $request->header('X-Kivara-Signature', '');
        $provided = str_starts_with($provided, 'sha256=') ? substr($provided, 7) : $provided;
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($provided === '' || !hash_equals($expected, strtolower($provided))) {
            abort(401, 'Assinatura do webhook inválida.');
        }

        return $next($request);
    }
}
