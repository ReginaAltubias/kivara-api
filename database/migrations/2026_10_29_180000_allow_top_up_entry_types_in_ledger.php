<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `ledger_entries.entry_type` era um enum fechado sem `top_up`, mas os carregamentos
     * (WalletController::settleTopUp e PartnerWalletController::webhook) gravam esse tipo:
     * todas as liquidacoes falhavam. Passa a texto livre (validado pela aplicacao).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_entry_type_check');
        }

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('entry_type', 40)->change();
        });
    }

    public function down(): void
    {
        // Sem reversao: voltar ao enum fechado voltaria a impedir os carregamentos.
    }
};
