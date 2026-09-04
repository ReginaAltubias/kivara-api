<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos para suportar o fluxo de avaliação pedagógica B2B:
     * - O aluno submete uma prova (submission_url, submitted_at)
     * - O estado passa de null → pending_review → approved / rejected
     * - O professor aprova/rejeita e pode deixar feedback
     * - O sistema injeta XP/Coins apenas após aprovação
     */
    public function up(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            // Quem submeteu (teen user_id ou child_profile_id já existe)
            // Suporte a adolescentes (tabela users) além de crianças
            $table->uuid('user_id')->nullable()->after('child_profile_id')
                  ->comment('Adolescente (users.id). Preenchido em alternativa ao child_profile_id.');

            // Estado do fluxo de avaliação
            $table->enum('status', [
                'not_started',
                'in_progress',
                'pending_review',
                'approved',
                'rejected',
            ])->default('not_started')->after('completed');

            // Prova enviada pelo aluno
            $table->string('submission_url')->nullable()->after('status')
                  ->comment('URL do ficheiro/foto submetida pelo aluno como prova.');
            $table->text('submission_note')->nullable()->after('submission_url')
                  ->comment('Nota opcional que o aluno inclui com a submissão.');
            $table->timestamp('submitted_at')->nullable()->after('submission_note');

            // Avaliação do professor
            $table->uuid('reviewed_by')->nullable()->after('submitted_at')
                  ->comment('users.id do professor que avaliou.');
            $table->text('feedback')->nullable()->after('reviewed_by')
                  ->comment('Nota de incentivo/rejeição deixada pelo professor.');
            $table->timestamp('reviewed_at')->nullable()->after('feedback');

            // Recompensas atribuídas
            $table->integer('xp_awarded')->default(0)->after('total_coins_earned');
            $table->integer('coins_awarded')->default(0)->after('xp_awarded');

            // Índices para a fila de avaliação do professor
            $table->index('user_id');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['submitted_at']);
            $table->dropColumn([
                'user_id',
                'status',
                'submission_url',
                'submission_note',
                'submitted_at',
                'reviewed_by',
                'feedback',
                'reviewed_at',
                'xp_awarded',
                'coins_awarded',
            ]);
        });
    }
};
