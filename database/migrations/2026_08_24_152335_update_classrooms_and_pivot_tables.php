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
        // Alterar tabela classrooms
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('grade_level')->after('name')->nullable();
            $table->renameColumn('academic_year', 'year');
        });

        // Alterar tabela classroom_user (pivô)
        Schema::table('classroom_user', function (Blueprint $table) {
            // 1. Adicionar o user_id para os Professores
            $table->uuid('user_id')->nullable()->after('classroom_id');
            
            // 2. Adicionar o papel (role) na turma
            $table->string('role')->default('student')->after('child_profile_id');
            
            // 3. Tornar o child_profile_id nullable
            $table->uuid('child_profile_id')->nullable()->change();
            
            // Adicionar a Foreign Key para o user_id
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classroom_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'role']);
            $table->uuid('child_profile_id')->nullable(false)->change();
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->renameColumn('year', 'academic_year');
            $table->dropColumn('grade_level');
        });
    }
};
