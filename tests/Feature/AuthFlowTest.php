<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_activate_login_and_me(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Maria Pai', 'email' => 'maria@exemplo.ao',
            'password' => 'Segredo123', 'password_confirmation' => 'Segredo123',
        ])->assertCreated()->assertJsonPath('data.requires_activation', true)->assertJsonMissingPath('data.token');

        Mail::assertQueued(RegistrationOtpMail::class, fn ($m) => $m->hasTo('maria@exemplo.ao'));

        $user = User::where('email', 'maria@exemplo.ao')->first();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->tenant_id);
        $this->assertNotNull($user->family_id);

        // email repetido, papel invalido e palavra-passe fraca
        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'maria@exemplo.ao', 'password' => 'Segredo123', 'password_confirmation' => 'Segredo123'])->assertStatus(422);
        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'x@exemplo.ao', 'password' => 'Segredo123', 'password_confirmation' => 'Segredo123', 'role' => 'admin'])->assertStatus(422);
        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'y@exemplo.ao', 'password' => '123', 'password_confirmation' => '123'])
            ->assertStatus(422)->assertJsonPath('status', 'error');

        // conta inativa nao entra
        $this->postJson('/api/v1/auth/login', ['email' => 'maria@exemplo.ao', 'password' => 'Segredo123'])
            ->assertStatus(403)->assertJsonPath('error_code', 'account_not_activated');

        $this->postJson('/api/v1/auth/verify-activation', ['email' => 'maria@exemplo.ao', 'code' => '000000'])->assertStatus(422);

        $token = $this->postJson('/api/v1/auth/verify-activation', ['email' => 'maria@exemplo.ao', 'code' => $user->activation_code])
            ->assertOk()->json('data.token');
        $this->assertTrue($user->fresh()->is_active);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => 'maria@exemplo.ao', 'password' => 'Segredo123'])
            ->assertStatus(201)->assertJsonPath('data.user.tenant_id', $user->tenant_id)->assertJsonPath('data.user.must_change_password', false);
    }

    public function test_resend_activation_never_resets_password_of_active_accounts(): void
    {
        Mail::fake();
        $active = User::create(['name' => 'A', 'email' => 'ativo@exemplo.ao', 'password' => 'Original123', 'role' => 'parent', 'is_active' => true]);
        $hash = $active->password;

        $this->postJson('/api/v1/auth/resend-activation', ['email' => 'ativo@exemplo.ao'])->assertOk();
        $this->postJson('/api/v1/auth/resend-activation', ['email' => 'nao@existe.ao'])->assertOk();
        Mail::assertNothingQueued();
        $this->assertSame($hash, $active->fresh()->password);

        // registo proprio: novo OTP, mesma palavra-passe
        $this->postJson('/api/v1/auth/register', ['name' => 'N', 'email' => 'novo@exemplo.ao', 'password' => 'Escolhida1', 'password_confirmation' => 'Escolhida1']);
        $pending = User::where('email', 'novo@exemplo.ao')->first();
        $oldCode = $pending->activation_code;
        $oldHash = $pending->password;

        $this->postJson('/api/v1/auth/resend-activation', ['email' => 'novo@exemplo.ao'])->assertOk();
        Mail::assertQueued(RegistrationOtpMail::class, 2);
        $this->assertSame($oldHash, $pending->fresh()->password);
        $this->assertNotNull($pending->fresh()->activation_code);

        // conta criada por admin: novas credenciais
        $admin = User::create(['name' => 'P', 'email' => 'prov@exemplo.ao', 'password' => 'Temp12345', 'role' => 'teacher', 'is_active' => true, 'must_change_password' => true, 'activation_code' => '111111']);
        $this->postJson('/api/v1/auth/resend-activation', ['email' => 'prov@exemplo.ao'])->assertOk();
        Mail::assertQueued(\App\Mail\AccountActivationMail::class, 1);
        $this->assertNotSame('111111', $admin->fresh()->activation_code);

        $this->postJson('/api/v1/auth/login', ['email' => 'prov@exemplo.ao', 'password' => 'Temp12345'])->assertStatus(401);
    }

    public function test_login_returns_must_change_password_flag(): void
    {
        User::create(['name' => 'M', 'email' => 'm@exemplo.ao', 'password' => 'Temp12345', 'role' => 'teacher', 'is_active' => true, 'must_change_password' => true]);

        $this->postJson('/api/v1/auth/login', ['email' => 'm@exemplo.ao', 'password' => 'Temp12345'])
            ->assertStatus(201)->assertJsonPath('data.must_change_password', true)->assertJsonPath('data.user.must_change_password', true);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::create(['name' => 'S', 'email' => 's@exemplo.ao', 'password' => 'Segredo123', 'role' => 'parent', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 's@exemplo.ao', 'password' => 'Segredo123'])
            ->assertStatus(403)->assertJsonPath('error_code', 'account_inactive');
    }

    public function test_forgot_and_reset_password(): void
    {
        Mail::fake();
        $user = User::create(['name' => 'R', 'email' => 'r@exemplo.ao', 'password' => 'Antiga1234', 'role' => 'parent', 'is_active' => true]);

        // nao revela se o email existe
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nao@existe.ao'])->assertOk();
        Mail::assertNothingQueued();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'r@exemplo.ao'])->assertOk();
        $token = null;
        Mail::assertQueued(PasswordResetMail::class, function ($m) use (&$token) {
            preg_match('/token=([^&]+)/', $m->resetUrl, $mm);
            $token = $mm[1];

            return $m->hasTo('r@exemplo.ao');
        });

        $this->postJson('/api/v1/auth/reset-password', ['email' => 'r@exemplo.ao', 'token' => 'errado', 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345'])
            ->assertStatus(422)->assertJsonPath('error_code', 'invalid_reset_token');

        $this->postJson('/api/v1/auth/reset-password', ['email' => 'r@exemplo.ao', 'token' => $token, 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345'])->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => 'r@exemplo.ao', 'password' => 'Antiga1234'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'r@exemplo.ao', 'password' => 'Nova12345'])->assertStatus(201);

        // token so pode ser usado uma vez
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'r@exemplo.ao', 'token' => $token, 'password' => 'Outra12345', 'password_confirmation' => 'Outra12345'])->assertStatus(422);
    }

    public function test_errors_use_clean_json_envelope_and_login_is_rate_limited(): void
    {
        $this->getJson('/api/v1/rota-que-nao-existe')->assertStatus(404)
            ->assertJsonPath('status', 'error')->assertJsonMissingPath('trace')->assertJsonMissingPath('file');

        $this->getJson('/api/v1/auth/me')->assertStatus(401)->assertJsonPath('status', 'error');

        $this->postJson('/api/v1/auth/login', [])->assertStatus(422)
            ->assertJsonPath('message', 'Dados de validação inválidos.')
            ->assertJsonPath('errors.password.0', 'O campo palavra-passe é obrigatório quando pin não está presente.');

        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson('/api/v1/auth/login', ['email' => 'a@b.ao', 'password' => 'x']);
        }
        $last->assertStatus(429)->assertJsonPath('status', 'error');
    }
}
