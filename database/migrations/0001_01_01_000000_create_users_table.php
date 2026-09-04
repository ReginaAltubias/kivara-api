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
        // TENANTS TABLE - Multi-Tenant Support (Schools, Families, Partners)
        // ====================================================================
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->enum('tenant_type', ['family', 'school', 'institutional_partner', 'partner'])->default('family');
            $table->string('currency', 3)->default('AOA');
            $table->boolean('is_active')->default(true);
            $table->string('email')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('domain')->nullable()->unique();
            $table->text('description')->nullable();
            $table->uuid('subscription_tier_id')->nullable();
            $table->timestamps();
            
            $table->index('tenant_type');
            $table->index('is_active');
        });

        // ====================================================================
        // FAMILIES TABLE - Groups of users (Parents + Children)
        // ====================================================================
        Schema::create('families', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('name')->nullable();
            $table->string('currency', 3)->default('AOA');
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('tenant_id');
        });

        // ====================================================================
        // USERS TABLE - Multi-tenant users (Parents, Teachers, Coordinators, Admin)
        // ====================================================================
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->comment('Null = família, UUID = escola/parceiro');
            $table->uuid('family_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'coordinator', 'teacher', 'parent', 'teen', 'yang'])->default('parent');
            $table->rememberToken();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('family_id')->references('id')->on('families')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('family_id');
            $table->index('role');
        });

        // ====================================================================
        // PASSWORD RESET & SESSIONS
        // ====================================================================
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('families');
        Schema::dropIfExists('tenants');
    }
};
