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
        // 1. Tabela school_rewards (Catálogo de Recompensas da Escola)
        Schema::create('school_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->default('🎁');
            $table->integer('price_in_coins')->nullable()->comment('0 ou null = prémio simbólico exclusivo de desafios; > 0 = disponível na loja');
            $table->integer('stock')->nullable()->comment('null = stock ilimitado');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('is_active');
        });

        // 2. Adicionar school_reward_id à tabela partner_challenges
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->foreignUuid('school_reward_id')->nullable()->after('school_reward_description')->constrained('school_rewards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->dropForeign(['school_reward_id']);
            $table->dropColumn('school_reward_id');
        });

        Schema::dropIfExists('school_rewards');
    }
};
