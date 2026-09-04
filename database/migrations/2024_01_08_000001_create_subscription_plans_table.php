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
        // SUBSCRIPTION_PLANS TABLE - Planos Freemium/Premium (B2C + B2B)
        // ====================================================================
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('type', ['free', 'family', 'school', 'partner']);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('AOA');
            $table->integer('max_children')->nullable()->comment('null = ilimitado');
            $table->integer('max_classrooms')->nullable()->comment('null = ilimitado');
            $table->json('features')->nullable()->comment('JSONB: serviços extras');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
            $table->index('monthly_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
