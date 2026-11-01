<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\LearningObject;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\PartnerProgram;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerModulesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Partner, 2: PartnerProgram, 3: PartnerChallenge, 4: Wallet} */
    private function partner(string $name): array
    {
        $partner = Partner::create(['name' => $name, 'status' => 'approved', 'is_active' => true, 'contact_email' => strtolower($name) . '@p.ao']);
        $user = User::create(['name' => "Dir {$name}", 'email' => strtolower($name) . '@p.ao', 'password' => 'secret-pass-123', 'role' => 'partner', 'is_active' => true, 'partner_id' => $partner->id]);
        $tenant = Tenant::create(['name' => "Tenant {$name}", 'tenant_type' => 'partner']);
        $wallet = Wallet::create(['tenant_id' => $tenant->id, 'walletable_id' => $partner->id, 'walletable_type' => Partner::class, 'currency' => 'KVC', 'balance' => 0]);
        WalletLedger::move($wallet, 'credit', 1000000, 'top_up', 'Carregamento', 'seed-' . $name);

        $program = PartnerProgram::create(['partner_id' => $partner->id, 'title' => "Programa {$name}", 'allocated_budget' => 100000, 'status' => 'active']);
        $challenge = PartnerChallenge::create([
            'partner_id' => $partner->id, 'program_id' => $program->id, 'partner_name' => $name, 'title' => 'Poupar', 'goal_type' => 'total_xp',
            'goal_target' => 100, 'reward_pool' => 20000, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'active',
        ]);

        return [$user, $partner, $program, $challenge, $wallet->fresh()];
    }

    public function test_reports_are_real_aggregates_by_province(): void
    {
        [$user, , , $challenge] = $this->partner('Banco');
        $school = Tenant::create(['name' => 'Escola Luanda', 'tenant_type' => 'school', 'province' => 'Luanda']);
        $teen = User::create(['name' => 'Aluno', 'email' => 'a@e.ao', 'password' => 'secret-pass-123', 'role' => 'teen', 'is_active' => true, 'tenant_id' => $school->id]);
        $class = Classroom::create(['tenant_id' => $school->id, 'name' => '9A', 'grade_level' => '9', 'year' => '2026', 'teacher_id' => $teen->id]);
        ClassroomUser::create(['classroom_id' => $class->id, 'user_id' => $teen->id, 'role' => 'student']);

        Sanctum::actingAs($user, ['access-api']);
        // sem inscricoes: tudo a zero (antes: 84,5 %, 1 480 alunos, "Luanda" inventado)
        $this->getJson('/api/v1/partner/reports/metrics')->assertOk()
            ->assertJsonPath('data.total_students', 0)->assertJsonPath('data.avg_completion_rate', 0)->assertJsonPath('data.total_schools', 0);
        $this->getJson('/api/v1/partner/reports/chart-data')->assertOk()->assertJsonCount(6, 'data.time_series')->assertJsonCount(0, 'data.geo');

        \DB::table('classroom_challenge')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'partner_challenge_id' => $challenge->id, 'classroom_id' => $class->id, 'current_progress' => 50, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $lo = LearningObject::create(['tenant_id' => $school->id, 'title' => 'x', 'type' => 'quiz', 'topic' => 'saving', 'reward_xp' => 10, 'reward_coins' => 0]);
        UserProgress::create(['user_id' => $teen->id, 'learning_object_id' => $lo->id, 'completed' => true, 'total_xp_earned' => 40]);

        $this->getJson('/api/v1/partner/reports/metrics')->assertOk()
            ->assertJsonPath('data.total_students', 1)->assertJsonPath('data.total_schools', 1)
            ->assertJsonPath('data.avg_completion_rate', 100)->assertJsonPath('data.total_missions_completed', 1);
        $this->getJson('/api/v1/partner/reports/chart-data')->assertOk()
            ->assertJsonPath('data.geo.0.province', 'Luanda')->assertJsonPath('data.geo.0.students', 1)->assertJsonPath('data.geo.0.percentage', 100)
            ->assertJsonPath('data.time_series.5.students', 1)->assertJsonPath('data.time_series.5.xp', 40);
    }

    public function test_prizes_flow_from_partner_to_school_with_isolation(): void
    {
        [$user, , , $challenge] = $this->partner('Banco');
        [$other] = $this->partner('Outro');
        $school = Tenant::create(['name' => 'Escola P', 'tenant_type' => 'school', 'province' => 'Huíla']);
        $coord = User::create(['name' => 'Coord', 'email' => 'c@e.ao', 'password' => 'secret-pass-123', 'role' => 'coordinator', 'is_active' => true, 'tenant_id' => $school->id]);
        $class = Classroom::create(['tenant_id' => $school->id, 'name' => '5A', 'grade_level' => '5', 'year' => '2026', 'teacher_id' => $coord->id]);
        $foreign = Classroom::create(['tenant_id' => $school->id, 'name' => 'Fora', 'grade_level' => '5', 'year' => '2026', 'teacher_id' => $coord->id]);
        \DB::table('classroom_challenge')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'partner_challenge_id' => $challenge->id, 'classroom_id' => $class->id, 'current_progress' => 10, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($user, ['access-api']);
        $payload = ['partner_challenge_id' => $challenge->id, 'classroom_id' => $class->id, 'title' => '5 portáteis', 'category' => 'material', 'value_aoa' => 1500000];
        $this->postJson('/api/v1/partner/prizes', ['classroom_id' => $foreign->id] + $payload)->assertStatus(422); // turma nao inscrita
        $id = $this->postJson('/api/v1/partner/prizes', $payload)->assertCreated()
            ->assertJsonPath('data.status', 'processing')->assertJsonPath('data.school_name', 'Escola P')->assertJsonPath('data.province', 'Huíla')->json('data.id');
        $this->getJson('/api/v1/partner/prizes')->assertOk()->assertJsonCount(1, 'data');

        // a escola ve o premio
        Sanctum::actingAs($coord, ['access-api']);
        $this->getJson('/api/v1/school/prizes')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sponsor', 'Banco');

        // outro parceiro nao mexe
        Sanctum::actingAs($other, ['access-api']);
        $this->getJson('/api/v1/partner/prizes')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/partner/prizes/{$id}", ['status' => 'delivered'])->assertStatus(404);

        Sanctum::actingAs($user, ['access-api']);
        $this->patchJson("/api/v1/partner/prizes/{$id}", ['status' => 'delivered'])->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertNotNull(\App\Models\ChallengePrize::find($id)->delivery_date);
        $this->deleteJson("/api/v1/partner/prizes/{$id}")->assertStatus(422); // entregue nao se elimina
    }

    public function test_program_invitations_for_families_and_schools(): void
    {
        [$user, , $program] = $this->partner('Banco');
        $tenant = Tenant::create(['name' => 'Familia', 'tenant_type' => 'family']);
        $family = \App\Models\Family::create(['tenant_id' => $tenant->id, 'name' => 'F', 'currency' => 'AOA']);
        $parent = User::create(['name' => 'Pai', 'email' => 'pai@e.ao', 'password' => 'secret-pass-123', 'role' => 'parent', 'is_active' => true, 'tenant_id' => $tenant->id, 'family_id' => $family->id]);
        $school = Tenant::create(['name' => 'Escola', 'tenant_type' => 'school']);
        $coord = User::create(['name' => 'Coord', 'email' => 'c@e.ao', 'password' => 'secret-pass-123', 'role' => 'coordinator', 'is_active' => true, 'tenant_id' => $school->id]);

        Sanctum::actingAs($user, ['access-api']);
        $code = $this->postJson("/api/v1/partner/programs/{$program->id}/invite", ['invite_type' => 'household'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')->json('data.code');
        $this->postJson("/api/v1/partner/programs/{$program->id}/invite", ['invite_type' => 'household'])->assertStatus(409);
        $schoolCode = $this->postJson("/api/v1/partner/programs/{$program->id}/invite", ['target_type' => 'school'])->assertCreated()->json('data.code');
        $this->getJson("/api/v1/partner/programs/{$program->id}/invitations")->assertOk()->assertJsonCount(2, 'data');

        // a familia ve e valida o convite, mas nao o da escola
        Sanctum::actingAs($parent, ['access-api']);
        $this->getJson('/api/v1/invitations/mine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.program_name', 'Programa Banco');
        $this->getJson("/api/v1/partner/invitations/{$code}/validate")->assertOk()->assertJsonPath('valid', true)->assertJsonPath('partner_name', 'Banco');
        $this->postJson('/api/v1/partner/invitations/accept', ['code' => $schoolCode])->assertStatus(403);
        $this->postJson('/api/v1/partner/invitations/accept', ['code' => $code])->assertOk();
        $this->postJson('/api/v1/partner/invitations/accept', ['code' => $code])->assertStatus(422); // ja usado
        $this->getJson("/api/v1/partner/invitations/{$code}/validate")->assertJsonPath('valid', false);

        // a escola recusa o seu
        Sanctum::actingAs($coord, ['access-api']);
        $this->postJson('/api/v1/partner/invitations/reject', ['code' => $schoolCode])->assertOk();

        // o parceiro ve os estados e nao revoga convites ja resolvidos
        Sanctum::actingAs($user, ['access-api']);
        $rows = $this->getJson('/api/v1/partner/invitations')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['accepted', 'rejected'], array_column($rows, 'status'));
        $this->assertSame('Pai', collect($rows)->firstWhere('status', 'accepted')['accepted_by_name']);
        $this->deleteJson('/api/v1/partner/invitations/' . $rows[0]['id'])->assertStatus(422);

        // famílias nao criam convites
        Sanctum::actingAs($parent, ['access-api']);
        $this->postJson("/api/v1/partner/programs/{$program->id}/invite", [])->assertStatus(403);
    }

    public function test_subscription_is_paid_with_the_partner_wallet_and_profile_is_editable(): void
    {
        [$user, $partner, , , $wallet] = $this->partner('Banco');
        $plan = SubscriptionPlan::create(['name' => 'Corporate Anual', 'type' => 'partner', 'monthly_price' => 15000, 'annual_price' => 150000, 'currency' => 'AOA', 'is_active' => true, 'features' => ['reports']]);
        $family = SubscriptionPlan::create(['name' => 'Familia', 'type' => 'family', 'monthly_price' => 1, 'annual_price' => 10, 'currency' => 'AOA', 'is_active' => true]);

        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/subscription')->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson('/api/v1/partner/subscription/subscribe', ['tier_id' => $family->id])->assertStatus(422);
        // anual = 150 000 Kz = 1 500 000 KVC > 1 000 000 KVC da carteira: saldo insuficiente, nada muda
        $this->postJson('/api/v1/partner/subscription/subscribe', ['tier_id' => $plan->id, 'billing_period' => 'yearly'])->assertStatus(422);
        $this->assertEquals(1000000.0, (float) $wallet->fresh()->balance);
        $this->getJson('/api/v1/partner/subscription')->assertJsonPath('data.is_active', false);

        // mensal = 15 000 Kz = 150 000 KVC
        $this->postJson('/api/v1/partner/subscription/subscribe', ['tier_id' => $plan->id, 'billing_period' => 'monthly'])->assertOk();
        $this->assertEquals(850000.0, (float) $wallet->fresh()->balance);
        $this->getJson('/api/v1/partner/subscription')->assertJsonPath('data.is_active', true)->assertJsonPath('data.tier.name', 'Corporate Anual');
        $this->getJson('/api/v1/partner/subscription/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.amount', 15000);
        $this->postJson('/api/v1/partner/subscription/cancel')->assertOk();
        $this->getJson('/api/v1/partner/subscription')->assertJsonPath('data.is_active', false);

        // perfil institucional
        $this->patchJson('/api/v1/partner/profile', ['nif' => '5000123456', 'province' => 'Benguela', 'director_name' => 'Maria', 'director_email' => 'm@banco.ao'])
            ->assertOk()->assertJsonPath('data.province', 'Benguela');
        $this->getJson('/api/v1/partner/profile')->assertJsonPath('data.nif', '5000123456')->assertJsonPath('data.director_name', 'Maria');
        $this->patchJson('/api/v1/partner/profile', ['contact_email' => 'invalido'])->assertStatus(422);
    }
}
