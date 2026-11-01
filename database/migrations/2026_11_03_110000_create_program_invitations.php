<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->uuid('program_id');
            $table->string('invite_type', 20); // household | school
            $table->string('code', 20)->unique();
            $table->string('status', 20)->default('pending'); // pending | accepted | rejected | revoked
            $table->timestamp('expires_at')->nullable();
            $table->uuid('accepted_by_user_id')->nullable();
            $table->uuid('accepted_household_id')->nullable();
            $table->uuid('accepted_school_tenant_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'program_id']);
            $table->index(['invite_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_invitations');
    }
};
