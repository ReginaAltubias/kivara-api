<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test calcular saldo dinamicamente
     */
    public function test_calculate_balance_dynamically()
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);

        // Criar crédito de 100
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'idempotency_key' => 'test-1',
            'direction' => 'credit',
            'entry_type' => 'allowance',
            'amount' => 100,
            'description' => 'Allowance',
        ]);

        // Criar débito de 30
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'idempotency_key' => 'test-2',
            'direction' => 'debit',
            'entry_type' => 'purchase',
            'amount' => 30,
            'description' => 'Purchase',
        ]);

        $balance = $wallet->calculateBalance();
        $this->assertEquals(70, $balance);
    }

    /**
     * Test que ledger é append-only
     */
    public function test_ledger_entry_is_append_only()
    {
        $entry = LedgerEntry::factory()->create();

        // Tentar atualizar deve lançar exceção
        $this->expectException(\Exception::class);
        $entry->update(['amount' => 999]);
    }

    /**
     * Test que ledger não pode ser deletado
     */
    public function test_ledger_entry_cannot_be_deleted()
    {
        $entry = LedgerEntry::factory()->create();

        $this->expectException(\Exception::class);
        $entry->delete();
    }
}
