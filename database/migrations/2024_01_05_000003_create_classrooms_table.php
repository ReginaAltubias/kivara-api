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
        // CLASSROOMS TABLE - Turmas (Escola + Professor + Alunos)
        // ====================================================================
        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('academic_year')->comment('ex: 2024/2025');
            $table->uuid('teacher_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('tenant_id');
            $table->index('teacher_id');
            $table->index('is_active');
        });

        // ====================================================================
        // CLASSROOM_USER TABLE - Pivot: Alunos per Turma (Many-to-Many)
        // ====================================================================
        Schema::create('classroom_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classroom_id');
            $table->uuid('child_profile_id');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->unique(['classroom_id', 'child_profile_id']);
            $table->index('classroom_id');
            $table->index('child_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_user');
        Schema::dropIfExists('classrooms');
    }
};
