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
        // STREAKS TABLE - Dias Consecutivos de Atividade
        // ====================================================================
        Schema::create('streaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->integer('current_streak')->default(0);
            $table->integer('best_streak')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->unique('child_profile_id');
            $table->index('child_profile_id');
        });

        // ====================================================================
        // LEVEL_UP_REQUESTS TABLE - Portão Parental
        // ====================================================================
        // Quando criança atinge XP necessário, cria este registro aguardando aprovação parental
        Schema::create('level_up_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->uuid('parent_id');
            $table->integer('from_phase');
            $table->integer('to_phase');
            $table->integer('current_xp');
            $table->integer('required_xp');
            $table->string('reason')->comment('age_requirement, xp_milestone, tutorial_completion, etc');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('child_profile_id');
            $table->index('parent_id');
            $table->index('status');
            $table->index('created_at');
        });

        // ====================================================================
        // ALLOWANCE_CONFIGS TABLE - Mesadas (Subsídios Periódicos)
        // ====================================================================
        // ⚠️ Regra de Negócio: Apenas crianças com idade >= 12 anos
        Schema::create('allowance_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->uuid('parent_id');
            $table->decimal('base_amount', 15, 2);
            $table->enum('frequency', ['weekly', 'monthly'])->default('monthly');
            $table->date('next_payment_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('child_profile_id');
            $table->index('child_profile_id');
            $table->index('parent_id');
            $table->index('is_active');
        });

        // ====================================================================
        // DREAM_VAULTS TABLE - Cofres de Poupança (Metas Financeiras)
        // ====================================================================
        Schema::create('dream_vaults', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->string('icon')->comment('Emoji ou ícone');
            $table->text('description')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable()->comment('Taxa mensal (ex: 1.50 = 1.5%)');
            $table->boolean('is_active')->default(true);
            $table->date('target_date')->nullable();
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->index('child_profile_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dream_vaults');
        Schema::dropIfExists('allowance_configs');
        Schema::dropIfExists('level_up_requests');
        Schema::dropIfExists('streaks');
    }
};
