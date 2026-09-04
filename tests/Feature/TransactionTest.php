<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test criar transação com idempotency
     */
    public function test_create_transaction_with_idempotency()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['balance' => 1000]);
        $token = $user->createToken('test')->plainTextToken;

        $idempotencyKey = 'test-key-' . time();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/finance/transactions', [
                'wallet_id' => $wallet->id,
                'amount' => 100,
                'direction' => 'debit',
                'entry_type' => 'purchase',
                'description' => 'Test purchase',
            ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['data' => ['id', 'amount', 'balance_after']]);
    }

    /**
     * Test idempotency - segunda requisição retorna 200
     */
    public function test_idempotency_returns_existing_transaction()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['balance' => 1000]);
        $token = $user->createToken('test')->plainTextToken;
        $idempotencyKey = 'duplicate-key-' . time();

        // Primeira requisição
        $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/finance/transactions', [
                'wallet_id' => $wallet->id,
                'amount' => 100,
                'direction' => 'debit',
                'entry_type' => 'purchase',
                'description' => 'Test purchase',
            ]);

        // Segunda requisição com mesma chave
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/finance/transactions', [
                'wallet_id' => $wallet->id,
                'amount' => 100,
                'direction' => 'debit',
                'entry_type' => 'purchase',
                'description' => 'Test purchase',
            ]);

        $response->assertStatus(200)
            ->assertJson(['is_duplicate' => true]);
    }

    /**
     * Test erro: saldo insuficiente
     */
    public function test_insufficient_funds_error()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['balance' => 50]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Idempotency-Key', 'test-key')
            ->postJson('/api/v1/finance/transactions', [
                'wallet_id' => $wallet->id,
                'amount' => 100,
                'direction' => 'debit',
                'entry_type' => 'purchase',
                'description' => 'Test purchase',
            ]);

        $response->assertStatus(400);
    }

    /**
     * Test listar transações
     */
    public function test_list_transactions()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/finance/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [],
                'pagination' => ['current_page', 'per_page', 'total'],
            ]);
    }
}
