<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\DreamVault;
use App\Models\Family;
use App\Models\LedgerEntry;
use App\Models\Reward;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentModulesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ChildProfile, 2: Wallet, 3: Wallet} */
    private function family(string $name, float $parentBalance = 0): array
    {
        $tenant = Tenant::create(['name' => "Familia {$name}", 'tenant_type' => 'family']);
        $family = Family::create(['tenant_id' => $tenant->id, 'name' => $name, 'currency' => 'AOA']);
        $parent = User::create([
            'name' => "Pai {$name}", 'email' => strtolower($name) . '@teste.ao', 'password' => 'secret-pass-123',
            'role' => 'parent', 'is_active' => true, 'tenant_id' => $tenant->id, 'family_id' => $family->id,
        ]);
        $child = ChildProfile::create([
            'parent_id' => $parent->id, 'family_id' => $family->id, 'tenant_id' => $tenant->id,
            'name' => "Filho {$name}", 'username' => 'kid_' . strtolower($name), 'auth_method' => 'pin',
        ]);
        $parentWallet = Wallet::create(['tenant_id' => $tenant->id, 'walletable_id' => $parent->id, 'walletable_type' => User::class, 'balance' => 0, 'currency' => 'AOA']);
        $childWallet = Wallet::create(['tenant_id' => $tenant->id, 'walletable_id' => $child->id, 'walletable_type' => ChildProfile::class, 'balance' => 0, 'currency' => 'AOA']);
        if ($parentBalance > 0) {
            WalletLedger::move($parentWallet, 'credit', $parentBalance, 'top_up', 'Carregamento', 'seed-' . $name);
        }

        return [$parent, $child, $parentWallet->fresh(), $childWallet];
    }

    public function test_mission_lifecycle_emits_kvc_to_the_child_wallet(): void
    {
        [$parent, $child, , $childWallet] = $this->family('Ana');
        [$other] = $this->family('Bruno');

        Sanctum::actingAs($parent, ['access-api']);
        $id = $this->postJson('/api/v1/missions', [
            'title' => 'Poupar 500', 'type' => 'saving', 'reward' => 100, 'kiva_points_reward' => 20,
            'child_profile_id' => $child->id, 'parent_profile_id' => $parent->id,
        ])->assertCreated()->assertJsonPath('data.status', 'available')->json('data.id');

        $this->getJson('/api/v1/missions?parent_profile_id=' . $parent->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child_profile_id', $child->id)->assertJsonPath('data.0.reward', 100);
        $this->patchJson("/api/v1/missions/{$id}", ['title' => 'Poupar 600', 'reward' => 120])->assertOk()->assertJsonPath('data.reward', 120);
        $this->postJson("/api/v1/missions/{$id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/missions/{$id}/complete")->assertOk()
            ->assertJsonPath('reward_coins', 120)->assertJsonPath('reward_points', 20)->assertJsonPath('new_balance', 120);
        $this->assertEquals(12.0, (float) $childWallet->fresh()->balance); // 120 KVC = 12 AOA
        $this->assertSame(20, $child->fresh()->total_xp);
        $this->postJson("/api/v1/missions/{$id}/complete")->assertStatus(422); // sem pagamento duplo
        $this->assertSame(1, LedgerEntry::where('wallet_id', $childWallet->id)->count());

        $this->getJson('/api/v1/stats/emission')->assertOk()->assertJsonPath('data.emission_stats.emitted_this_month', 120);

        // outra familia nao mexe nesta missao
        Sanctum::actingAs($other, ['access-api']);
        $this->patchJson("/api/v1/missions/{$id}", ['title' => 'x'])->assertStatus(404);
        $this->postJson("/api/v1/missions/{$id}/complete")->assertStatus(404);
        $this->deleteJson("/api/v1/missions/{$id}")->assertStatus(404);
    }

    public function test_wallets_household_transactions_and_guardians(): void
    {
        [$parent, $child, $parentWallet, $childWallet] = $this->family('Ana', 1000);
        [$other, , $otherWallet] = $this->family('Bruno', 500);
        $householdId = $parent->family_id;

        Sanctum::actingAs($parent, ['access-api']);
        $wallets = $this->getJson('/api/v1/wallets')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertEqualsCanonicalizing([10000.0, 0.0], array_map('floatval', array_column($wallets, 'balance')));
        $this->getJson("/api/v1/wallets/{$parentWallet->id}/balance")->assertOk()->assertJsonPath('data.balance', 10000);
        $this->getJson("/api/v1/wallets/{$parentWallet->id}/transactions?limit=5")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/wallets/{$otherWallet->id}/balance")->assertStatus(404);

        $this->getJson("/api/v1/households/{$householdId}/transactions")->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.amount', 10000)->assertJsonPath('data.0.direction', 'credit');
        $this->getJson("/api/v1/households/{$other->family_id}/transactions")->assertStatus(404);

        // /auth/me expoe o household
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.household_id', $householdId);

        $this->postJson("/api/v1/households/{$householdId}/invite", ['email' => 'segundo@teste.ao'])->assertCreated();
        $guardians = $this->getJson("/api/v1/households/{$householdId}/guardians")->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame('primary', $guardians[0]['role']);
        $second = User::where('email', 'segundo@teste.ao')->firstOrFail();

        // o secundario nao convida nem remove; o principal remove-o
        Sanctum::actingAs($second, ['access-api']);
        $this->postJson("/api/v1/households/{$householdId}/invite", ['email' => 'terceiro@teste.ao'])->assertStatus(403);
        Sanctum::actingAs($parent, ['access-api']);
        $this->deleteJson("/api/v1/households/{$householdId}/guardians/{$parent->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/households/{$householdId}/guardians/{$second->id}")->assertOk();
        $this->getJson("/api/v1/households/{$householdId}/guardians")->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/weekly-summary', ['householdId' => $householdId])->assertOk()
            ->assertJsonPath('children.0.profileId', $child->id)->assertJsonPath('children.0.balance', 0);
    }

    public function test_subscription_is_paid_with_the_wallet_and_limits_children(): void
    {
        [$parent, , $parentWallet] = $this->family('Ana', 100);
        $plan = SubscriptionPlan::create([
            'name' => 'Familia Premium', 'type' => 'family', 'monthly_price' => 500, 'annual_price' => 5000,
            'currency' => 'AOA', 'max_children' => 1, 'features' => ['reports'], 'is_active' => true, 'extra_child_price' => 50,
        ]);

        Sanctum::actingAs($parent, ['access-api']);
        $this->getJson('/api/v1/subscription')->assertOk()->assertJsonPath('tier.name', 'Free');

        // saldo insuficiente: nada muda
        $this->postJson('/api/v1/subscription/subscribe', ['tier_id' => $plan->id])->assertStatus(422);
        $this->assertNull(Tenant::find($parent->tenant_id)->subscription_tier_id);

        WalletLedger::move($parentWallet, 'credit', 1000, 'top_up', 'Carregamento', 'seed-2');
        $this->postJson('/api/v1/subscription/subscribe', ['tier_id' => $plan->id, 'billing_period' => 'monthly'])->assertOk();
        $this->assertEquals(600.0, (float) $parentWallet->fresh()->balance);
        $this->getJson('/api/v1/subscription')->assertOk()->assertJsonPath('tier.name', 'Familia Premium')->assertJsonPath('tier.max_children', 1);
        $this->getJson('/api/v1/subscription/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'paid');

        // plano com 1 crianca: a segunda exige vaga extra
        $this->postJson('/api/v1/children', ['display_name' => 'Segundo', 'auth_method' => 'pin', 'pin' => '1234'])
            ->assertStatus(422)->assertJsonPath('code', 'child_limit_reached');
        $this->postJson('/api/v1/subscription/add-extra-child', ['quantity' => 1])->assertOk();
        $this->assertEquals(550.0, (float) $parentWallet->fresh()->balance);
        $this->postJson('/api/v1/children', ['display_name' => 'Segundo', 'auth_method' => 'pin', 'pin' => '1234'])->assertCreated();

        $this->postJson('/api/v1/subscription/cancel')->assertOk();
        $this->getJson('/api/v1/subscription')->assertJsonPath('tier.name', 'Free');
    }

    public function test_budget_exceptions_and_dream_comments(): void
    {
        [$parent, $child, , $childWallet] = $this->family('Ana');
        [$other] = $this->family('Bruno');
        WalletLedger::move($childWallet, 'credit', 100, 'mission_reward', 'Missão', 'seed-c');
        $reward = Reward::create(['title' => 'Bola', 'cost' => 300, 'child_id' => $child->id, 'icon' => '⚽']);

        Sanctum::actingAs($parent, ['access-api']);
        $id = $this->postJson('/api/v1/wallets/budget-exceptions', [
            'child_profile_id' => $child->id, 'parent_profile_id' => $parent->id, 'reward_id' => $reward->id, 'amount' => 300, 'reason' => 'Quero muito',
        ])->assertCreated()->assertJsonPath('data.reward_name', 'Bola')->json('data.id');
        $this->postJson('/api/v1/wallets/budget-exceptions', ['child_profile_id' => $child->id, 'reward_id' => $reward->id, 'amount' => 300])->assertStatus(409);
        $this->getJson('/api/v1/wallets/budget-exceptions?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/wallets/budget-exceptions?reward_id={$reward->id}&status=pending")->assertJsonPath('data.id', $id);

        // 300 KVC = 30 AOA; a crianca tem 100 AOA
        $this->patchJson("/api/v1/wallets/budget-exceptions/{$id}", ['action' => 'approve'])->assertOk()->assertJsonPath('reward_name', 'Bola');
        $this->assertEquals(70.0, (float) $childWallet->fresh()->balance);
        $this->patchJson("/api/v1/wallets/budget-exceptions/{$id}", ['action' => 'reject'])->assertStatus(422);

        Sanctum::actingAs($other, ['access-api']);
        $this->getJson('/api/v1/wallets/budget-exceptions')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/wallets/budget-exceptions/{$id}", ['action' => 'reject'])->assertStatus(404);

        $vault = DreamVault::create(['child_profile_id' => $child->id, 'name' => 'Bicicleta', 'target_amount' => 1000, 'icon' => '🚲']);
        Sanctum::actingAs($parent, ['access-api']);
        $cid = $this->postJson("/api/v1/dream-vaults/{$vault->id}/comments", ['text' => 'Força!', 'emoji' => '💪'])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/dream-vaults/{$vault->id}/comments")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.parent_profile.display_name', 'Pai Ana');

        Sanctum::actingAs($other, ['access-api']);
        $this->getJson("/api/v1/dream-vaults/{$vault->id}/comments")->assertStatus(404);
        $this->postJson("/api/v1/dream-vaults/{$vault->id}/comments", ['text' => 'x'])->assertStatus(404);
        Sanctum::actingAs($parent, ['access-api']);
        $this->deleteJson("/api/v1/dream-vaults/{$vault->id}/comments/{$cid}")->assertOk();
    }
}
