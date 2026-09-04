<?php

use App\Enums\ContentStatus;
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
        Schema::create('mission_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->enum('task_type', ['quiz', 'real_world_action', 'upload_proof']);
            $table->json('validation_rules')->nullable();
            $table->integer('base_coin_reward')->default(0);
            $table->integer('base_xp_reward')->default(0);
            $table->string('status')->default(ContentStatus::DRAFT->value);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('lesson_id')
                ->references('id')
                ->on('lessons')
                ->nullOnDelete();

            $table->index('lesson_id');
            $table->index('task_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_templates');
    }
};
