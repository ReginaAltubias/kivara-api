<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\PartnerProgram;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerFollowupTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Partner, 2: PartnerProgram, 3: PartnerChallenge} */
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

        return [$user, $partner, $program, $challenge];
    }

    public function test_limits_follow_the_plan_and_programs_are_capped(): void
    {
        [$user] = $this->partner('Banco');
        $plan = SubscriptionPlan::create(['name' => 'Corporate', 'type' => 'partner', 'monthly_price' => 1, 'annual_price' => 10, 'currency' => 'AOA', 'is_active' => true, 'max_programs' => 2, 'max_children' => 500]);

        Sanctum::actingAs($user, ['access-api']);
        $this->getJson('/api/v1/partner/limits')->assertOk()->assertJsonPath('data.max_programs', 20)->assertJsonPath('data.tier_name', 'Parceiro');

        $this->postJson('/api/v1/partner/subscription/subscribe', ['tier_id' => $plan->id, 'billing_period' => 'monthly'])->assertOk();
        $this->getJson('/api/v1/partner/limits')->assertOk()
            ->assertJsonPath('data.max_programs', 2)->assertJsonPath('data.max_children', 500)
            ->assertJsonPath('data.tier_name', 'Corporate')->assertJsonPath('data.used_programs', 1);

        // ja existe 1 programa: o 2.o cabe, o 3.o excede o plano
        $this->postJson('/api/v1/partner/programs', ['title' => 'Segundo', 'allocated_budget' => 0])->assertCreated();
        $this->postJson('/api/v1/partner/programs', ['title' => 'Terceiro', 'allocated_budget' => 0])
            ->assertStatus(422)->assertJsonPath('code', 'program_limit_reached');
    }

    public function test_school_invitation_enrols_its_classrooms_in_program_challenges(): void
    {
        [$user, , $program, $challenge] = $this->partner('Banco');
        $school = Tenant::create(['name' => 'Escola', 'tenant_type' => 'school']);
        $coord = User::create(['name' => 'Coord', 'email' => 'c@e.ao', 'password' => 'secret-pass-123', 'role' => 'coordinator', 'is_active' => true, 'tenant_id' => $school->id]);
        Classroom::create(['tenant_id' => $school->id, 'name' => '4A', 'grade_level' => '4', 'year' => '2026', 'teacher_id' => $coord->id]);
        Classroom::create(['tenant_id' => $school->id, 'name' => '5B', 'grade_level' => '5', 'year' => '2026', 'teacher_id' => $coord->id]);

        Sanctum::actingAs($user, ['access-api']);
        $code = $this->postJson("/api/v1/partner/programs/{$program->id}/invite", ['invite_type' => 'school'])->assertCreated()->json('data.code');
        $this->assertSame(0, DB::table('classroom_challenge')->count());

        Sanctum::actingAs($coord, ['access-api']);
        $this->postJson('/api/v1/partner/invitations/accept', ['code' => $code])->assertOk();
        $this->assertSame(2, DB::table('classroom_challenge')->where('partner_challenge_id', $challenge->id)->count());

        // um desafio criado depois tambem inscreve as turmas da escola que ja aceitou
        Sanctum::actingAs($user, ['access-api']);
        $second = $this->postJson('/api/v1/partner/challenges', ['program_id' => $program->id, 'title' => 'Novo', 'goal_type' => 'total_xp', 'goal_target' => 50, 'reward_pool' => 1000])
            ->assertCreated()->json('data.id');
        $this->assertSame(2, DB::table('classroom_challenge')->where('partner_challenge_id', $second)->count());
    }

    public function test_prize_proof_file_upload(): void
    {
        Storage::fake('public');
        [$user, , , $challenge] = $this->partner('Banco');
        [$other] = $this->partner('Outro');
        $school = Tenant::create(['name' => 'Escola', 'tenant_type' => 'school']);
        $class = Classroom::create(['tenant_id' => $school->id, 'name' => '5A', 'grade_level' => '5', 'year' => '2026', 'teacher_id' => $user->id]);
        DB::table('classroom_challenge')->insert(['id' => (string) Str::uuid(), 'partner_challenge_id' => $challenge->id, 'classroom_id' => $class->id, 'current_progress' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($user, ['access-api']);
        $id = $this->postJson('/api/v1/partner/prizes', ['partner_challenge_id' => $challenge->id, 'classroom_id' => $class->id, 'title' => 'Kit', 'category' => 'material', 'value_aoa' => 10])
            ->assertCreated()->json('data.id');

        $json = ['Accept' => 'application/json'];
        $this->post("/api/v1/partner/prizes/{$id}/proof", ['proof' => UploadedFile::fake()->create('guia.exe', 10)], $json)->assertStatus(422);
        $url = $this->post("/api/v1/partner/prizes/{$id}/proof", ['proof' => UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf')], $json)
            ->assertOk()->json('data.proof_document_url');
        $this->assertStringContainsString('/prizes/', $url);
        Storage::disk('public')->assertExists(substr($url, strpos($url, 'prizes/')));

        Sanctum::actingAs($other, ['access-api']);
        $this->post("/api/v1/partner/prizes/{$id}/proof", ['proof' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], $json)->assertStatus(404);
    }
}
