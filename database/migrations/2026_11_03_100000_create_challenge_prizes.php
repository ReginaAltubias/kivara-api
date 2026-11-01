<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_prizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('partner_id');
            $table->uuid('partner_challenge_id');
            $table->uuid('classroom_id');
            $table->uuid('tenant_id')->nullable();
            $table->string('title');
            $table->string('category', 20)->default('material');
            $table->decimal('value_aoa', 14, 2)->default(0);
            $table->string('status', 20)->default('processing');
            $table->date('delivery_date')->nullable();
            $table->string('recipient_representative')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('tracking_code', 60)->nullable();
            $table->string('proof_document_url')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_prizes');
    }
};
