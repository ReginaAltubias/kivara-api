<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ====================================================================
        // LEDGER_ENTRIES TABLE - Livro Razão (Append-Only, Imutável)
        // ====================================================================
        // Esta é a tabela mais crítica do sistema. NUNCA atualizar/deletar registos.
        // Saldos são calculados dinamicamente: SUM(credit) - SUM(debit)
        // ====================================================================
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->string('idempotency_key')->unique()->comment('Chave de idempotência para evitar double-spending');
            $table->enum('direction', ['credit', 'debit'])->comment('Entrada (credit) ou Saída (debit)');
            $table->enum('entry_type', ['allowance', 'task_reward', 'mission_reward', 'purchase', 'vault_deposit', 'vault_interest', 'donation', 'adjustment']);
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->json('metadata')->nullable()->comment('Dados adicionais: referência de transação externa, etc');
            $table->timestamp('created_at')->nullable();
            $table->index('wallet_id');
            $table->index('created_at');
            $table->index('entry_type');
            
            // ⚠️ NÃO TEM updated_at - Append-Only!
        });

        // ====================================================================
        // RISK_FLAGS TABLE - Detecção de Fraude
        // ====================================================================
        Schema::create('risk_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->uuid('ledger_entry_id')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->string('flag_type')->comment('duplicate_transaction, unusual_pattern, velocity_exceeded, etc');
            $table->text('description')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->uuid('reviewed_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->foreign('ledger_entry_id')->references('id')->on('ledger_entries')->nullOnDelete();
            $table->index('wallet_id');
            $table->index('risk_level');
            $table->index('is_reviewed');
        });

        // ====================================================================
        // AUDIT_LOG TABLE - GDPR/SOX Compliance (Auditoria completa)
        // ====================================================================
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('event')->comment('transaction_created, wallet_frozen, level_up_approved, etc');
            $table->string('model_type')->nullable();
            $table->uuid('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
            $table->index('model_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('risk_flags');
        Schema::dropIfExists('ledger_entries');
    }
};
