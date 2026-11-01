<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\Family;
use App\Models\LedgerEntry;
use App\Models\LevelUpRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ChildProfile, 2: Wallet} */
    private function family(string $name): array
    {
        $tenant = Tenant::create(['name' => "Familia {$name}", 'tenant_type' => 'family']);
        $family = Family::create(['tenant_id' => $tenant->id, 'name' => $name, 'currency' => 'AOA']);
        $parent = User::create([
            'name' => "Pai {$name}", 'email' => strtolower($name) . '@teste.ao', 'password' => 'secret-pass-123',
            'role' => 'parent', 'is_active' => true, 'tenant_id' => $tenant->id, 'family_id' => $family->id,
        ]);
        $child = ChildProfile::create([
            'parent_id' => $parent->id, 'family_id' => $family->id, 'tenant_id' => $tenant->id,
            'name' => "Filho {$name}", 'username' => 'kid_' . strtolower($name), 'auth_method' => 'pin', 'total_xp' => 40,
        ]);
        $wallet = Wallet::create([
            'tenant_id' => $tenant->id, 'walletable_id' => $child->id, 'walletable_type' => ChildProfile::class,
            'balance' => 0, 'currency' => 'AOA',
        ]);
        LedgerEntry::create([
            'wallet_id' => $wallet->id, 'idempotency_key' => "seed-{$name}", 'direction' => 'credit',
            'entry_type' => 'allowance', 'amount' => 500, 'description' => 'Mesada', 'created_at' => now(),
        ]);

        return [$parent, $child, $wallet];
    }

    public function test_transactions_cannot_touch_other_families_wallets(): void
    {
        [$parentA, , $walletA] = $this->family('Ana');
        [$parentB, , $walletB] = $this->family('Bruno');

        Sanctum::actingAs($parentB, ['access-api']);

        // B nao ve nem movimenta a carteira de A
        $this->getJson('/api/v1/finance/transactions')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/finance/transactions?wallet_id={$walletA->id}")->assertOk()->assertJsonCount(0, 'data');

        $payload = ['wallet_id' => $walletA->id, 'amount' => 100, 'direction' => 'debit', 'entry_type' => 'purchase', 'description' => 'Roubo'];
        $this->postJson('/api/v1/finance/transactions', $payload, ['Idempotency-Key' => 'k-1'])->assertStatus(404);

        // creditos manuais (criar dinheiro) so o admin, mesmo na propria carteira
        $this->postJson('/api/v1/finance/transactions', ['wallet_id' => $walletB->id, 'amount' => 999999, 'direction' => 'credit', 'entry_type' => 'adjustment', 'description' => 'Dinheiro gratis'], ['Idempotency-Key' => 'k-2'])
            ->assertStatus(403);
        $this->postJson('/api/v1/finance/transactions', ['wallet_id' => $walletB->id, 'amount' => 10, 'direction' => 'debit', 'entry_type' => 'adjustment', 'description' => 'x'], ['Idempotency-Key' => 'k-3'])
            ->assertStatus(403);
        $this->assertSame(1, LedgerEntry::where('wallet_id', $walletB->id)->count());

        // debito legitimo na propria carteira
        $this->postJson('/api/v1/finance/transactions', ['wallet_id' => $walletB->id, 'amount' => 50, 'direction' => 'debit', 'entry_type' => 'purchase', 'description' => 'Livro'], ['Idempotency-Key' => 'k-4'])
            ->assertCreated();

        // reutilizar a chave de idempotencia de outra familia nao revela dados
        Sanctum::actingAs($parentA, ['access-api']);
        $this->postJson('/api/v1/finance/transactions', ['wallet_id' => $walletA->id, 'amount' => 20, 'direction' => 'debit', 'entry_type' => 'purchase', 'description' => 'Doce'], ['Idempotency-Key' => 'k-4'])
            ->assertStatus(409);

        // o admin pode ajustar
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@teste.ao', 'password' => 'secret-pass-123', 'role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin, ['access-api']);
        $this->postJson('/api/v1/finance/transactions', ['wallet_id' => $walletA->id, 'amount' => 100, 'direction' => 'credit', 'entry_type' => 'adjustment', 'description' => 'Ajuste'], ['Idempotency-Key' => 'k-5'])
            ->assertCreated();
        $this->getJson('/api/v1/finance/transactions')->assertOk()->assertJsonPath('pagination.total', 4);
    }

    public function test_child_summary_consents_levelups_and_missions_are_owner_only(): void
    {
        [$parentA, $childA] = $this->family('Ana');
        [$parentB, $childB] = $this->family('Bruno');
        $level = LevelUpRequest::create([
            'child_profile_id' => $childA->id, 'parent_id' => $parentA->id, 'from_phase' => 1, 'to_phase' => 2,
            'current_xp' => 40, 'required_xp' => 100, 'reason' => 'xp_milestone',
        ]);

        Sanctum::actingAs($parentA, ['access-api']);
        $this->getJson("/api/v1/children/{$childA->id}/summary")->assertOk()
            ->assertJsonPath('data.kiva_points', 40)->assertJsonPath('data.lessons_completed', 0)
            ->assertJsonPath('data.missions_completed', 0)->assertJsonPath('data.streak_days', 0);
        $this->postJson("/api/v1/consents/{$childA->id}/update", ['consent_type' => 'analytics', 'is_granted' => true])->assertOk();
        $this->getJson("/api/v1/consents/{$childA->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/parents/level-up-requests/{$level->id}")->assertOk();
        $this->postJson('/api/v1/missions', ['title' => 'Poupar', 'reward_amount' => 50, 'child_id' => $childA->id])->assertCreated();

        Sanctum::actingAs($parentB, ['access-api']);
        $this->getJson("/api/v1/children/{$childA->id}/summary")->assertStatus(404);
        $this->getJson("/api/v1/parent/children/{$childA->id}/summary")->assertStatus(404);
        $this->getJson("/api/v1/consents/{$childA->id}")->assertStatus(404);
        $this->postJson("/api/v1/consents/{$childA->id}/update", ['consent_type' => 'analytics', 'is_granted' => false])->assertStatus(404);
        $this->assertDatabaseHas('consents', ['child_profile_id' => $childA->id, 'is_granted' => 1]);
        $this->getJson("/api/v1/parents/level-up-requests/{$level->id}")->assertStatus(404);
        $this->postJson("/api/v1/parents/level-up-requests/{$level->id}/approve")->assertStatus(403);
        $this->postJson('/api/v1/missions', ['title' => 'Intruso', 'reward_amount' => 50, 'child_id' => $childA->id])->assertStatus(404);
        $this->postJson('/api/v1/missions', ['title' => 'Mass', 'reward_amount' => 5, 'child_id' => $childB->id, 'status' => 'completed', 'id' => 'x'])->assertCreated();
        $this->getJson('/api/v1/missions')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_parent_cannot_attach_child_to_arbitrary_school(): void
    {
        [$parent] = $this->family('Ana');
        $school = Tenant::create(['name' => 'Escola Alvo', 'tenant_type' => 'school']);

        Sanctum::actingAs($parent, ['access-api']);
        $this->postJson('/api/v1/children', [
            'display_name' => 'Novo', 'auth_method' => 'pin', 'pin' => '1234', 'school_tenant_id' => $school->id,
        ])->assertStatus(422);

        $this->postJson('/api/v1/children', ['display_name' => 'Novo', 'auth_method' => 'pin', 'pin' => '1234'])->assertCreated();
        $this->assertDatabaseMissing('child_profiles', ['name' => 'Novo', 'tenant_id' => $school->id]);
    }
}
