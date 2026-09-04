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
        Schema::create('insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('category');
            $table->text('actionable_advice')->nullable();
            $table->string('period')->default('weekly');
            $table->timestamps();

            $table->foreign('child_id')->references('id')->on('child_profiles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};
