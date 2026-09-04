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
        // 1. Tabela partner_programs (O Guarda-chuva corporativo da campanha)
        Schema::create('partner_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('allocated_budget')->default(0)->comment('Orçamento em KVC cativado da carteira geral do parceiro');
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            $table->index(['partner_id', 'status']);
        });

        // 2. Adicionar program_id à tabela partner_challenges
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->foreignUuid('program_id')->nullable()->after('partner_id')->constrained('partner_programs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropColumn('program_id');
        });

        Schema::dropIfExists('partner_programs');
    }
};
