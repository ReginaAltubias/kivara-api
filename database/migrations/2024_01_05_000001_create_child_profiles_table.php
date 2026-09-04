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
        // CHILD_PROFILES TABLE - Perfis de Crianças (usuários tipo "kid")
        // ====================================================================
        Schema::create('child_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id');
            $table->uuid('family_id');
            $table->uuid('tenant_id')->nullable()->comment('Ligação à escola (se estudar numa escola KIVARA)');
            $table->string('name');
            $table->string('username')->unique();
            $table->string('pin')->nullable()->comment('Hashed PIN para login simplificado (máx 4 dígitos)');
            $table->integer('kivara_phase_id')->default(1)->comment('Fase actual do RPG (1-10)');
            $table->integer('total_xp')->default(0);
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('parent_id');
            $table->index('family_id');
            $table->index('tenant_id');
            $table->index('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_profiles');
    }
};
