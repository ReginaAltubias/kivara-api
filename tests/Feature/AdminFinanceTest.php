<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@teste.ao', 'password' => 'secret-pass-123',
            'role' => 'admin', 'is_active' => true,
        ]);
        Sanctum::actingAs($admin, ['access-api']);

        return $admin;
    }

    public function test_currency_crud_and_public_listing(): void
    {
        $this->admin();

        // moedas por omissao criadas pela migration
        $this->getJson('/api/v1/admin/currencies')->assertOk()->assertJsonCount(3, 'data');

        $this->postJson('/api/v1/admin/currencies', ['code' => 'brl', 'name' => 'Real', 'symbol' => 'R$', 'decimal_places' => 2])
            ->assertCreated()->assertJsonPath('data.code', 'BRL')->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('currencies', ['code' => 'BRL', 'symbol' => 'R$']);

        $this->postJson('/api/v1/admin/currencies', ['code' => 'BRL', 'name' => 'Dup', 'symbol' => 'x'])->assertStatus(422);
        $this->postJson('/api/v1/admin/currencies', ['code' => 'ABCD', 'name' => 'Bad', 'symbol' => 'x'])->assertStatus(422);

        $this->putJson('/api/v1/admin/currencies/BRL', ['name' => 'Real brasileiro', 'decimal_places' => 3])
            ->assertOk()->assertJsonPath('data.name', 'Real brasileiro')->assertJsonPath('data.decimal_places', 3);

        $this->postJson('/api/v1/admin/currencies/BRL/toggle-active')->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/v1/currencies')->assertOk()->assertJsonCount(3, 'data'); // BRL inativa nao aparece
        $this->postJson('/api/v1/admin/currencies/BRL/toggle-active')->assertOk()->assertJsonPath('data.is_active', true);

        // moeda em uso por um tenant nao pode ser desativada nem eliminada
        Tenant::create(['name' => 'T', 'tenant_type' => 'school', 'currency' => 'BRL']);
        $this->postJson('/api/v1/admin/currencies/BRL/toggle-active')->assertStatus(409);
        $this->deleteJson('/api/v1/admin/currencies/BRL')->assertStatus(409);

        $this->postJson('/api/v1/admin/currencies', ['code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'decimal_places' => 0])->assertCreated();
        $this->deleteJson('/api/v1/admin/currencies/JPY')->assertOk();
        $this->assertDatabaseMissing('currencies', ['code' => 'JPY']);
    }

    public function test_exchange_rates_crud(): void
    {
        $this->admin();

        $id = $this->postJson('/api/v1/admin/exchange-rates', ['base_currency' => 'eur', 'target_currency' => 'AOA', 'rate' => 950.5])
            ->assertCreated()->assertJsonPath('data.base_currency', 'EUR')->json('data.id');
        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'EUR', 'to_currency' => 'AOA']);

        $this->postJson('/api/v1/admin/exchange-rates', ['base_currency' => 'EUR', 'target_currency' => 'AOA', 'rate' => 1])->assertStatus(422);
        $this->postJson('/api/v1/admin/exchange-rates', ['base_currency' => 'EUR', 'target_currency' => 'EUR', 'rate' => 1])->assertStatus(422);
        $this->postJson('/api/v1/admin/exchange-rates', ['base_currency' => 'EUR', 'target_currency' => 'XXX', 'rate' => 1])->assertStatus(422);
        $this->postJson('/api/v1/admin/exchange-rates', ['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 0])->assertStatus(422);

        $this->getJson('/api/v1/admin/exchange-rates')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/admin/exchange-rates/{$id}", ['rate' => 1000])->assertOk()->assertJsonPath('data.rate', 1000);

        // moeda com taxa associada nao pode ser eliminada
        $this->deleteJson('/api/v1/admin/currencies/EUR')->assertStatus(409);

        $this->deleteJson("/api/v1/admin/exchange-rates/{$id}")->assertOk();
        $this->assertDatabaseMissing('exchange_rates', ['id' => $id]);
    }

    public function test_invoices_crud_and_summary(): void
    {
        $this->admin();
        $tenant = Tenant::create(['name' => 'Escola Fin', 'tenant_type' => 'school']);

        $res = $this->postJson('/api/v1/admin/finance/invoices', [
            'tenant_id' => $tenant->id, 'amount' => 19987.5, 'currency' => 'AOA', 'type' => 'subscription',
            'plan_name' => 'Premium', 'description' => 'Mensal',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.tenant.name', 'Escola Fin');
        $id = $res->json('data.id');
        $this->assertStringStartsWith('REC-', $res->json('data.reference'));

        $this->postJson('/api/v1/admin/finance/invoices', ['tenant_id' => $tenant->id, 'amount' => -1])->assertStatus(422);
        $this->postJson('/api/v1/admin/finance/invoices', ['tenant_id' => $tenant->id, 'amount' => 5, 'currency' => 'ZZZ'])->assertStatus(422);

        $this->getJson('/api/v1/admin/finance/invoices?status=pending&search=escola')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/finance/invoices?status=paid')->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson("/api/v1/admin/finance/invoices/{$id}", ['status' => 'paid'])
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'paid']);
        $this->assertNotNull(\App\Models\Invoice::find($id)->paid_at);

        $this->patchJson("/api/v1/admin/finance/invoices/{$id}", ['amount' => 1])->assertStatus(409);
        $this->deleteJson("/api/v1/admin/finance/invoices/{$id}")->assertStatus(409);

        $this->getJson('/api/v1/admin/finance/invoices/summary')->assertOk()
            ->assertJsonPath('data.by_status.paid.count', 1)
            ->assertJsonPath('data.by_currency.0.currency', 'AOA')
            ->assertJsonCount(6, 'data.monthly');

        $id2 = $this->postJson('/api/v1/admin/finance/invoices', ['tenant_id' => $tenant->id, 'amount' => 10])->json('data.id');
        $this->deleteJson("/api/v1/admin/finance/invoices/{$id2}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $id2]);
    }

    public function test_wallet_freeze_profiles_regional_prices_and_plans(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::create(['name' => 'Fam', 'tenant_type' => 'family']);
        $owner = User::create(['name' => 'Dono', 'email' => 'dono@teste.ao', 'password' => 'secret-pass-123', 'role' => 'parent', 'tenant_id' => $tenant->id]);
        $wallet = Wallet::create([
            'tenant_id' => $tenant->id, 'walletable_id' => $owner->id, 'walletable_type' => User::class,
            'balance' => 250, 'currency' => 'AOA',
        ]);

        $this->getJson('/api/v1/admin/wallets?is_frozen=true')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/admin/wallets/{$wallet->id}/freeze", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/wallets/{$wallet->id}/freeze", ['reason' => 'Atividade suspeita'])
            ->assertOk()->assertJsonPath('data.is_frozen', true)->assertJsonPath('data.owner_name', 'Dono');
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'is_frozen' => 1, 'freeze_reason' => 'Atividade suspeita', 'frozen_by' => $admin->id]);

        $frozen = $this->getJson('/api/v1/admin/wallets?is_frozen=true')->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->getJson('/api/v1/admin/profiles?ids=' . $frozen['profile_id'] . ',' . $admin->id)->assertOk()->assertJsonCount(2, 'data');

        $this->postJson("/api/v1/admin/wallets/{$wallet->id}/unfreeze")->assertOk()->assertJsonPath('data.is_frozen', false);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'is_frozen' => 0, 'freeze_reason' => null]);

        $this->getJson('/api/v1/admin/stats')->assertOk()
            ->assertJsonPath('data.money_supply.total_in_wallets', 250)
            ->assertJsonPath('data.money_supply.wallet_count', 1);

        // planos + precos regionais
        $planId = $this->postJson('/api/v1/admin/subscription-plans', ['name' => 'Familiar', 'type' => 'family', 'monthly_price' => 10, 'annual_price' => 100])
            ->assertCreated()->json('data.id');

        $rp = $this->postJson('/api/v1/admin/regional-prices', [
            'tier_id' => $planId, 'currency_code' => 'eur', 'price_monthly' => 9, 'price_yearly' => 90, 'extra_child_price' => 1,
        ])->assertCreated()->assertJsonPath('data.currency_code', 'EUR')->json('data.id');
        $this->postJson('/api/v1/admin/regional-prices', ['tier_id' => $planId, 'currency_code' => 'EUR', 'price_monthly' => 1, 'price_yearly' => 1])->assertStatus(422);
        $this->putJson("/api/v1/admin/regional-prices/{$rp}", ['price_monthly' => 8])->assertOk()->assertJsonPath('data.price_monthly', 8);
        $this->getJson("/api/v1/admin/regional-prices?tier_id={$planId}")->assertOk()->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/admin/subscription-plans/{$planId}", ['monthly_price' => 12, 'is_active' => false])->assertOk();
        $this->assertDatabaseHas('subscription_plans', ['id' => $planId, 'is_active' => 0]);

        $tenant->update(['subscription_tier_id' => $planId]);
        $this->getJson('/api/v1/admin/subscription-plans')->assertOk()->assertJsonPath('data.0.tenant_count', 1);
        $this->deleteJson("/api/v1/admin/subscription-plans/{$planId}")->assertStatus(409);
        $tenant->update(['subscription_tier_id' => null]);

        $this->deleteJson("/api/v1/admin/regional-prices/{$rp}")->assertOk();
        $this->deleteJson("/api/v1/admin/subscription-plans/{$planId}")->assertOk();
        $this->assertDatabaseMissing('subscription_plans', ['id' => $planId]);
    }

    public function test_finance_routes_forbidden_for_non_admin(): void
    {
        $parent = User::create(['name' => 'P', 'email' => 'p@teste.ao', 'password' => 'secret-pass-123', 'role' => 'parent']);
        Sanctum::actingAs($parent, ['access-api']);

        foreach (['currencies', 'exchange-rates', 'finance/invoices', 'wallets', 'regional-prices'] as $path) {
            $this->getJson("/api/v1/admin/{$path}")->assertStatus(403);
        }
    }
}
