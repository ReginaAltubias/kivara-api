<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->uuid('subscription_tier_id')->nullable();
            $table->string('subscription_period', 10)->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
        });

        Schema::create('partner_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->uuid('tier_id')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('AOA');
            $table->string('billing_period', 20)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 80)->nullable();
            $table->timestamps();

            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_invoices');
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn(['subscription_tier_id', 'subscription_period', 'subscription_ends_at']));
    }
};
