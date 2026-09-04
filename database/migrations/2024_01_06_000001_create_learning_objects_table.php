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
        // LEARNING_OBJECTS TABLE - Lições, Quizzes, Puzzles
        // ====================================================================
        Schema::create('learning_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->comment('Se vinculada a escola específica, senão global');
            $table->string('title');
            $table->enum('type', ['story', 'quiz', 'puzzle', 'memory', 'drag_drop']);
            $table->string('topic')->comment('ex: savings, budgeting, risk_management');
            $table->integer('level')->default(1)->comment('1-10, correspondendo às fases do RPG');
            $table->text('description')->nullable();
            $table->json('content_data')->nullable()->comment('JSONB: perguntas, respostas, peças do puzzle, etc');
            $table->integer('reward_xp')->default(0);
            $table->integer('reward_coins')->default(0);
            $table->integer('minimum_score_percent')->default(70);
            $table->integer('estimated_duration_minutes')->default(10);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('type');
            $table->index('level');
            $table->index('topic');
            $table->index('is_active');
        });

        // ====================================================================
        // USER_PROGRESS TABLE - Rastreio de Progresso (User + LearningObject)
        // ====================================================================
        Schema::create('user_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->uuid('learning_object_id');
            $table->boolean('completed')->default(false);
            $table->integer('attempts')->default(0);
            $table->integer('best_score')->nullable();
            $table->integer('total_xp_earned')->default(0);
            $table->integer('total_coins_earned')->default(0);
            $table->json('last_attempt_data')->nullable()->comment('Respostas, timestamp, etc');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->foreign('learning_object_id')->references('id')->on('learning_objects')->cascadeOnDelete();
            $table->unique(['child_profile_id', 'learning_object_id']);
            $table->index('child_profile_id');
            $table->index('learning_object_id');
            $table->index('completed');
        });

        // ====================================================================
        // BADGES TABLE - Conquistas/Emblemas
        // ====================================================================
        Schema::create('badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();
            $table->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->integer('required_xp')->nullable()->comment('XP necessário para desbloquear');
            $table->string('trigger_type')->comment('lessons_completed_count, xp_milestone, streak_milestone, etc');
            $table->json('trigger_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('tier');
            $table->index('is_active');
        });

        // ====================================================================
        // USER_BADGES TABLE - Pivot: Child + Badge (Many-to-Many)
        // ====================================================================
        Schema::create('user_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->uuid('badge_id');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
            $table->unique(['child_profile_id', 'badge_id']);
            $table->index('child_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_progress');
        Schema::dropIfExists('learning_objects');
    }
};
