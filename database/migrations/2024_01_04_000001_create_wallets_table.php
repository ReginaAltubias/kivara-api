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
        // WALLETS TABLE - Carteiras (Polymorphic: Users ou ChildProfiles)
        // ====================================================================
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuidMorphs('walletable'); // Proprietário: User ou ChildProfile
            $table->decimal('balance', 15, 2)->default(0)->comment('Saldo em moeda local');
            $table->string('currency', 3)->default('AOA');
            $table->boolean('is_frozen')->default(false)->comment('Admin pode congelar transações');
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('walletable_type');
            $table->index('walletable_id');
            $table->unique(['walletable_id', 'walletable_type', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
