<?php

namespace Tests\Feature;

use App\Mail\PartnerDecisionMail;
use App\Models\Classroom;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@teste.ao', 'password' => 'secret-pass-123', 'role' => 'admin', 'is_active' => true]);
    }

    /** Regista e ativa um parceiro pela API publica (como o front). */
    private function registerPartner(string $email, string $institution): User
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Diretor ' . $institution, 'email' => $email, 'password' => 'Segredo123', 'password_confirmation' => 'Segredo123',
            'role' => 'partner', 'institution_name' => $institution, 'nif' => '5000' . random_int(100000, 999999), 'sector' => 'Banca',
        ])->assertCreated();

        $user = User::where('email', $email)->first();
        $this->postJson('/api/v1/auth/verify-activation', ['email' => $email, 'code' => $user->activation_code])->assertOk();

        return $user->fresh();
    }

    private function topUp(User $partnerUser, float $aoa): void
    {
        Sanctum::actingAs($partnerUser, ['access-api']);
        $ref = $this->postJson('/api/v1/partner/wallet/top-up', ['amount_aoa' => $aoa, 'payment_method' => 'multicaixa'])
            ->assertCreated()->json('data.provider_reference');

        config(['services.payments.webhook_secret' => 'segredo']);
        $body = json_encode(['provider_reference' => $ref]);
        $this->call('POST', '/api/v1/partner/wallet/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KIVARA_SIGNATURE' => hash_hmac('sha256', $body, 'segredo'),
        ], $body)->assertOk();
    }

    public function test_partner_registration_requires_admin_approval(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $user = $this->registerPartner('banco@exemplo.ao', 'Banco Exemplo');

        $partner = Partner::find($user->partner_id);
        $this->assertSame('pending', $partner->status);
        $this->assertSame('Banco Exemplo', $partner->name);

        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/status')->assertOk()->assertJsonPath('data.approval_status', 'pending');
        $this->getJson('/api/v1/partner/wallet')->assertStatus(403)->assertJsonPath('error_code', 'partner_pending_approval');
        $this->getJson('/api/v1/partner/programs')->assertStatus(403);

        // so o admin gere parceiros
        $this->getJson('/api/v1/admin/partners')->assertStatus(403);

        Sanctum::actingAs($admin, ['access-api']);
        $this->getJson('/api/v1/admin/partners?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Banco Exemplo');

        $this->postJson("/api/v1/admin/partners/{$partner->id}/approve")->assertOk()->assertJsonPath('data.approval_status', 'approved')->assertJsonPath('email_sent', true);
        Mail::assertQueued(PartnerDecisionMail::class, fn ($m) => $m->approved && $m->hasTo('banco@exemplo.ao'));
        $this->assertDatabaseHas('partners', ['id' => $partner->id, 'status' => 'approved']);

        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/wallet')->assertOk()->assertJsonPath('data.partner_name', 'Banco Exemplo');

        // suspensao
        Sanctum::actingAs($admin, ['access-api']);
        $this->patchJson("/api/v1/admin/partners/{$partner->id}/active", ['is_active' => false])->assertOk();
        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/wallet')->assertStatus(403)->assertJsonPath('error_code', 'partner_suspended');
    }

    public function test_rejected_partner_is_blocked_and_notified(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $user = $this->registerPartner('rej@exemplo.ao', 'Empresa Rejeitada');

        Sanctum::actingAs($admin, ['access-api']);
        $this->postJson("/api/v1/admin/partners/{$user->partner_id}/reject", ['reason' => 'NIF invalido'])
            ->assertOk()->assertJsonPath('data.rejection_reason', 'NIF invalido');
        Mail::assertQueued(PartnerDecisionMail::class, fn ($m) => !$m->approved);

        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/wallet')->assertStatus(403)->assertJsonPath('error_code', 'partner_rejected');
    }

    public function test_programs_challenges_budget_and_isolation(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $a = $this->registerPartner('a@exemplo.ao', 'Parceiro A');
        $b = $this->registerPartner('b@exemplo.ao', 'Parceiro B');
        Partner::whereIn('id', [$a->partner_id, $b->partner_id])->update(['status' => 'approved']);

        $this->topUp($a, 10000); // 100 000 KVC
        Sanctum::actingAs($a, ['access-api']);
        $this->getJson('/api/v1/partner/wallet')->assertOk()->assertJsonPath('data.balance_kvc', 100000);

        // saldo insuficiente
        $this->postJson('/api/v1/partner/programs', ['title' => 'Grande', 'allocated_budget' => 999999])->assertStatus(422);

        $programId = $this->postJson('/api/v1/partner/programs', [
            'title' => 'Poupar 2026', 'allocated_budget' => 60000, 'program_type' => 'family', 'children_target' => 300,
            'starts_at' => '2026-02-01', 'ends_at' => '2026-12-15',
        ])->assertCreated()->assertJsonPath('data.remaining_budget', 60000)->assertJsonPath('data.program_type', 'family')
            ->assertJsonPath('data.children_target', 300)->json('data.id');
        $this->getJson("/api/v1/partner/programs/{$programId}")->assertJsonPath('data.program_type', 'family');
        $this->patchJson("/api/v1/partner/programs/{$programId}", ['program_type' => 'school', 'children_target' => 120])->assertOk();
        $this->assertDatabaseHas('partner_programs', ['id' => $programId, 'program_type' => 'school', 'children_target' => 120]);
        $this->postJson('/api/v1/partner/programs', ['title' => 'Datas', 'allocated_budget' => 0, 'program_type' => 'x'])->assertStatus(422);
        $this->getJson('/api/v1/partner/wallet')->assertJsonPath('data.balance_kvc', 40000);

        // desafio cativa o premio
        $challengeId = $this->postJson('/api/v1/partner/challenges', [
            'program_id' => $programId, 'title' => 'Turma poupadora', 'goal_type' => 'total_xp', 'goal_target' => 500, 'reward_pool' => 25000,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/partner/challenges', [
            'program_id' => $programId, 'title' => 'Excede', 'goal_type' => 'total_xp', 'goal_target' => 10, 'reward_pool' => 40000,
        ])->assertStatus(422);
        $this->getJson("/api/v1/partner/programs/{$programId}")->assertJsonPath('data.remaining_budget', 35000);

        // desafio cancelado liberta o premio
        $this->patchJson("/api/v1/partner/challenges/{$challengeId}/status", ['status' => 'cancelled'])->assertOk();
        $this->getJson("/api/v1/partner/programs/{$programId}")->assertJsonPath('data.remaining_budget', 60000);
        $this->patchJson("/api/v1/partner/challenges/{$challengeId}/status", ['status' => 'active'])->assertOk();

        // participacoes (sem dados pessoais)
        $school = Tenant::create(['name' => 'Escola X', 'tenant_type' => 'school']);
        $classroom = Classroom::create(['tenant_id' => $school->id, 'name' => '5A', 'grade_level' => '5-7', 'year' => '2026/2027', 'teacher_id' => $admin->id]);
        PartnerChallenge::find($challengeId)->classrooms()->attach($classroom->id, ['id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'active', 'current_progress' => 120]);
        $this->getJson("/api/v1/partner/challenges/{$challengeId}/participations")->assertOk()
            ->assertJsonPath('data.0.classroom_name', '5A')->assertJsonPath('data.0.school_name', 'Escola X')->assertJsonPath('data.0.current_progress', 120);

        // parceiro B nao ve nem altera nada do A
        Sanctum::actingAs($b, ['access-api']);
        $this->getJson('/api/v1/partner/programs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/partner/challenges')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/partner/programs/{$programId}")->assertStatus(404);
        $this->patchJson("/api/v1/partner/challenges/{$challengeId}", ['title' => 'Roubado'])->assertStatus(404);
        $this->deleteJson("/api/v1/partner/challenges/{$challengeId}")->assertStatus(404);
        $this->deleteJson("/api/v1/partner/programs/{$programId}")->assertStatus(404);
        $this->getJson("/api/v1/partner/challenges/{$challengeId}/participations")->assertStatus(404);
        $this->getJson('/api/v1/partner/wallet')->assertJsonPath('data.balance_kvc', 0);

        // admin tem de indicar o parceiro
        Sanctum::actingAs($admin, ['access-api']);
        $this->getJson('/api/v1/partner/programs')->assertStatus(422);
        $this->getJson("/api/v1/partner/programs?partner_id={$a->partner_id}")->assertOk()->assertJsonCount(1, 'data');

        // programa com desafios nao se elimina; concluir devolve o orcamento por cativar
        Sanctum::actingAs($a, ['access-api']);
        $this->deleteJson("/api/v1/partner/programs/{$programId}")->assertStatus(409);
        $this->patchJson("/api/v1/partner/programs/{$programId}", ['status' => 'completed'])->assertOk();
        $this->assertDatabaseHas('partner_programs', ['id' => $programId, 'status' => 'completed', 'allocated_budget' => 25000]);
        $this->getJson('/api/v1/partner/wallet')->assertJsonPath('data.balance_kvc', 75000);

        // eliminar desafio e depois programa vazio devolve tudo
        $emptyId = $this->postJson('/api/v1/partner/programs', ['title' => 'Vazio', 'allocated_budget' => 30000])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/partner/wallet')->assertJsonPath('data.balance_kvc', 45000);
        $this->deleteJson("/api/v1/partner/programs/{$emptyId}")->assertOk();
        $this->assertDatabaseMissing('partner_programs', ['id' => $emptyId]);
        $this->getJson('/api/v1/partner/wallet')->assertJsonPath('data.balance_kvc', 75000);

        // admin: nao elimina parceiro com programas
        Sanctum::actingAs($admin, ['access-api']);
        $this->deleteJson("/api/v1/admin/partners/{$a->partner_id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/partners/{$b->partner_id}")->assertOk();
    }
}
