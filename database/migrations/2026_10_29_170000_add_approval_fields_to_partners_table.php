<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // Parceiros ja existentes ficam aprovados; novos registos entram como `pending`.
            $table->string('status', 20)->default('approved')->index();
            $table->string('nif', 30)->nullable();
            $table->string('sector', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('director_name')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'nif', 'sector', 'phone', 'country', 'city', 'address',
                'director_name', 'rejection_reason', 'reviewed_at', 'reviewed_by',
            ]);
        });
    }
};
