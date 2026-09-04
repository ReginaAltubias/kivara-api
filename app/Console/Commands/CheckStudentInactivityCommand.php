<?php

namespace App\Console\Commands;

use App\Services\EscalationNotificationService;
use Illuminate\Console\Command;

class CheckStudentInactivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kivara:check-student-inactivity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica a inatividade dos alunos e dispara escalamento de notificações (5d: Professor, 10d: Encarregado, 14d: Direção Escolar)';

    /**
     * Execute the console command.
     */
    public function handle(EscalationNotificationService $escalationService): int
    {
        $this->info('Iniciando verificação de inatividade e engajamento dos alunos...');

        $stats = $escalationService->evaluateAndEscalateAll();

        $this->table(
            ['Métrica', 'Total'],
            [
                ['Alunos Avaliados', $stats['evaluated']],
                ['Alertas a Professores (>= 5d)', $stats['notified_teacher']],
                ['Alertas a Encarregados (>= 10d)', $stats['notified_parent']],
                ['Alertas de Abandono à Direção (>= 14d)', $stats['notified_admin']],
            ]
        );

        $this->info('Motor de escalamento de inatividade executado com sucesso.');

        return Command::SUCCESS;
    }
}
