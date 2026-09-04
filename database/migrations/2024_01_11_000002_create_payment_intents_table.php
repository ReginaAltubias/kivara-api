<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->uuid('user_id');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('AOA');
            $table->string('payment_method');
            $table->string('phone', 20)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('provider_reference')->nullable()->unique();
            $table->uuid('ledger_entry_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('ledger_entry_id')->references('id')->on('ledger_entries')->nullOnDelete();

            $table->index(['wallet_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT ledger_entries_entry_type_check');
            DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY[
                'allowance'::character varying,
                'task_reward'::character varying,
                'mission_reward'::character varying,
                'purchase'::character varying,
                'vault_deposit'::character varying,
                'vault_interest'::character varying,
                'donation'::character varying,
                'adjustment'::character varying,
                'top_up'::character varying
            ])::text[])))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_intents');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT ledger_entries_entry_type_check');
            DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_entry_type_check CHECK (((entry_type)::text = ANY ((ARRAY[
                'allowance'::character varying,
                'task_reward'::character varying,
                'mission_reward'::character varying,
                'purchase'::character varying,
                'vault_deposit'::character varying,
                'vault_interest'::character varying,
                'donation'::character varying,
                'adjustment'::character varying
            ])::text[])))");
        }
    }
};
