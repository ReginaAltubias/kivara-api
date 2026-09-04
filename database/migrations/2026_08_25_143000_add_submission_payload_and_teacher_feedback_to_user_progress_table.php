<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona submission_payload, teacher_feedback e evaluated_at
     * para alinhamento estrito com o motor pedagógico de avaliação de missões.
     */
    public function up(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            if (!Schema::hasColumn('user_progress', 'submission_payload')) {
                $table->json('submission_payload')->nullable()->after('submission_url')
                      ->comment('Payload de submissão (texto, anexos, dados estruturados).');
            }

            if (!Schema::hasColumn('user_progress', 'teacher_feedback')) {
                $table->text('teacher_feedback')->nullable()->after('feedback')
                      ->comment('Feedback explícito do professor.');
            }

            if (!Schema::hasColumn('user_progress', 'evaluated_at')) {
                $table->timestamp('evaluated_at')->nullable()->after('reviewed_at')
                      ->comment('Data e hora em que a missão foi avaliada.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('user_progress', 'submission_payload')) {
                $columns[] = 'submission_payload';
            }
            if (Schema::hasColumn('user_progress', 'teacher_feedback')) {
                $columns[] = 'teacher_feedback';
            }
            if (Schema::hasColumn('user_progress', 'evaluated_at')) {
                $columns[] = 'evaluated_at';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
