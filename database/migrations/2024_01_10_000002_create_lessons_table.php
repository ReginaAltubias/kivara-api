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
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trail_id');
            $table->string('title');
            $table->enum('content_type', ['video', 'text', 'interactive']);
            $table->text('content_body');
            $table->string('media_url')->nullable();
            $table->integer('xp_reward')->default(0);
            $table->string('status')->default(ContentStatus::DRAFT->value);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('trail_id')
                ->references('id')
                ->on('trails')
                ->cascadeOnDelete();

            $table->index('trail_id');
            $table->index('status');
            $table->index(['trail_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
