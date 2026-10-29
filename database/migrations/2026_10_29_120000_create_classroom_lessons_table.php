<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classroom_id');
            $table->uuid('lesson_id');
            $table->timestamp('due_date')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->unique(['classroom_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_lessons');
    }
};
