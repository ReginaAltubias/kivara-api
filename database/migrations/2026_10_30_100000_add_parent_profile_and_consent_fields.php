<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->string('document_version', 20)->default('1.0');
            $table->string('method', 30)->default('digital');
            $table->json('consent_data')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->text('revocation_reason')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->text('avatar')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('language', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'country', 'gender', 'language']);
        });

        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn([
                'document_version', 'method', 'consent_data', 'consented_at',
                'expires_at', 'revoked_at', 'revoked_by', 'revocation_reason',
            ]);
        });
    }
};
