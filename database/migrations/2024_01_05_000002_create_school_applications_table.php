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
        // SCHOOL_APPLICATIONS TABLE - Candidaturas de Escolas (B2B Onboarding)
        // ====================================================================
        Schema::create('school_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('nif')->unique();
            $table->enum('school_type', ['public', 'private', 'community'])->default('private');
            $table->string('education_level')->comment('primary, lower_secondary, upper_secondary, vocational, mixed');
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_email');
            $table->string('website')->nullable();
            $table->string('director_name')->nullable();
            $table->string('director_phone')->nullable();
            $table->string('director_email')->nullable();
            $table->text('motivation')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->uuid('tenant_id')->nullable()->comment('Preenchido quando aprovada (ligação à escola criada)');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_applications');
    }
};
