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
        Schema::create('missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reward_type')->default('kvc');
            $table->decimal('reward_amount', 12, 2)->default(0);
            $table->uuid('child_id');
            $table->dateTime('deadline')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('child_id')->references('id')->on('child_profiles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
