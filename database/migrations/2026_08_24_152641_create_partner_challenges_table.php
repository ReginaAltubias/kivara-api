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
        Schema::create('partner_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('partner_name');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('goal_type', ['total_xp', 'lessons_completed', 'savings_amount']);
            $table->integer('goal_target')->default(0);
            $table->integer('reward_pool')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('classroom_challenge', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classroom_id');
            $table->uuid('partner_challenge_id');
            $table->integer('current_progress')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('partner_challenge_id')->references('id')->on('partner_challenges')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_challenge');
        Schema::dropIfExists('partner_challenges');
    }
};
