<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        '/api/v1/finance/wallet/webhooks/multicaixa',
        '/api/v1/school/subscription/webhook',
        '/api/v1/partner/wallet/webhook',
    ];

    private function signed(string $url, array $payload, string $secret, bool $prefix = false)
    {
        $body = json_encode($payload);
        $sig = hash_hmac('sha256', $body, $secret);

        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KIVARA_SIGNATURE' => ($prefix ? 'sha256=' : '') . $sig,
        ], $body);
    }

    public function test_webhooks_fail_closed_without_configured_secret(): void
    {
        config(['services.payments.webhook_secret' => null]);

        foreach (self::ROUTES as $url) {
            $this->postJson($url, ['provider_reference' => 'x', 'status' => 'completed'])
                ->assertStatus(503)->assertJsonPath('status', 'error');
        }
    }

    public function test_webhooks_reject_missing_or_wrong_signature(): void
    {
        config(['services.payments.webhook_secret' => 'segredo-de-teste']);

        foreach (self::ROUTES as $url) {
            $this->postJson($url, ['provider_reference' => 'x'])->assertStatus(401);
            $this->withHeaders(['X-Kivara-Signature' => 'deadbeef'])->postJson($url, ['provider_reference' => 'x'])->assertStatus(401);
            $this->signed($url, ['provider_reference' => 'x'], 'outro-segredo')->assertStatus(401);
        }
    }

    public function test_valid_signature_reaches_the_handler(): void
    {
        config(['services.payments.webhook_secret' => 'segredo-de-teste']);

        // Passa o middleware: o handler responde com a sua propria validacao (422/404), nao 401/503.
        $this->signed('/api/v1/school/subscription/webhook', [], 'segredo-de-teste')->assertStatus(422);
        $this->signed('/api/v1/partner/wallet/webhook', [], 'segredo-de-teste', prefix: true)->assertStatus(422);
        $this->signed('/api/v1/finance/wallet/webhooks/multicaixa', [], 'segredo-de-teste')->assertStatus(422);
        $this->signed('/api/v1/school/subscription/webhook', ['reference' => 'nao-existe'], 'segredo-de-teste')->assertStatus(404);
    }

    public function test_cors_allows_configured_origins_only(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'capacitor://localhost',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'capacitor://localhost');

        $resp = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://site-malicioso.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNotSame('https://site-malicioso.example', $resp->headers->get('Access-Control-Allow-Origin'));
    }
}
