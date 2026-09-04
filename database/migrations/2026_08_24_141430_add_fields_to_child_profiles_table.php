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
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->string('nickname')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('avatar')->nullable();
            $table->decimal('monthly_budget', 12, 2)->default(0);
            $table->decimal('daily_spend_limit', 12, 2)->default(0);
            $table->enum('auth_method', ['pin', 'email_password'])->default('pin');
            $table->integer('product_phase')->default(1);
            $table->enum('dashboard_role', ['child', 'teen', 'jovem'])->default('child');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'date_of_birth', 'avatar', 'monthly_budget', 
                'daily_spend_limit', 'auth_method', 'product_phase', 
                'dashboard_role', 'email', 'password'
            ]);
        });
    }
};
