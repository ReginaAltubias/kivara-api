<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\User;
use App\Models\UserProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscalationNotificationService
{
    /**
     * Avalia o engajamento de todos os estudantes e dispara o escalamento de inatividade:
     * - >= 5 dias: Notificação ao Professor (AlunoEngagement: em_risco)
     * - >= 10 dias: Notificação ao Encarregado de Educação
     * - >= 14 dias: Notificação de Abandono à Direção Escolar (AlunoEngagement: abandonou)
     */
    public function evaluateAndEscalateAll(): array
    {
        $stats = [
            'evaluated' => 0,
            'notified_teacher' => 0,
            'notified_parent' => 0,
            'notified_admin' => 0,
        ];

        // Avalia ChildProfiles (crianças)
        $children = ChildProfile::with(['family', 'classrooms.teachers'])->get();
        foreach ($children as $child) {
            $stats['evaluated']++;
            $days = $this->calculateDaysOfInactivity($child);

            $this->applyEscalationRules($child, $days, $stats);
        }

        // Avalia Usuários com role teen/student
        $teens = User::whereIn('role', ['teen', 'yang', 'student'])->get();
        foreach ($teens as $teen) {
            $stats['evaluated']++;
            $days = $this->calculateDaysOfInactivity($teen);

            $this->applyEscalationRules($teen, $days, $stats);
        }

        return $stats;
    }

    /**
     * Calcula os dias desde a última atividade pedagógica registrada.
     */
    public function calculateDaysOfInactivity($student): int
    {
        $studentId = $student->id;
        
        // Busca último progresso em lição
        $lastProgress = UserProgress::where('user_id', $studentId)
            ->orWhere('child_profile_id', $studentId)
            ->latest('updated_at')
            ->first();

        $lastActiveAt = $lastProgress?->updated_at ?? $student->created_at ?? now()->subDays(20);

        return (int) Carbon::parse($lastActiveAt)->diffInDays(now());
    }

    /**
     * Aplica as 3 regras de escalamento escalonado.
     */
    protected function applyEscalationRules($student, int $daysInactive, array &$stats): void
    {
        $studentName = $student->name ?? $student->nickname ?? 'Estudante';

        if ($daysInactive >= 14) {
            // Nível 3: Abandono -> Notificar Direção / Gestão Escolar
            $this->notifySchoolAdmin($student, $daysInactive);
            $stats['notified_admin']++;
            Log::warning("Escalamento Nível 3 (Abandono - {$daysInactive} dias): Aluno {$studentName} ({$student->id})");
        } elseif ($daysInactive >= 10) {
            // Nível 2: Inatividade Grave -> Notificar Encarregado de Educação
            $this->notifyParent($student, $daysInactive);
            $stats['notified_parent']++;
            Log::info("Escalamento Nível 2 (Encarregado - {$daysInactive} dias): Aluno {$studentName} ({$student->id})");
        } elseif ($daysInactive >= 5) {
            // Nível 1: Alerta Preventivo -> Notificar Professor
            $this->notifyTeacher($student, $daysInactive);
            $stats['notified_teacher']++;
            Log::info("Escalamento Nível 1 (Professor - {$daysInactive} dias): Aluno {$studentName} ({$student->id})");
        }
    }

    protected function notifyTeacher($student, int $days): void
    {
        // Cria notificação na tabela se existir, ou loga
        if (DB::getSchemaBuilder()->hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\StudentInactivityRiskNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $student->teacher_id ?? $student->tenant_id ?? (string) \Illuminate\Support\Str::uuid(),
                'data' => json_encode([
                    'level' => 'teacher',
                    'status' => 'em_risco',
                    'student_id' => $student->id,
                    'student_name' => $student->name ?? $student->nickname ?? 'Estudante',
                    'days_inactive' => $days,
                    'message' => "O aluno está sem atividade há {$days} dias. Considere enviar um lembrete.",
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function notifyParent($student, int $days): void
    {
        if (DB::getSchemaBuilder()->hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\StudentInactivityParentNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $student->user_id ?? $student->family_id ?? (string) \Illuminate\Support\Str::uuid(),
                'data' => json_encode([
                    'level' => 'parent',
                    'status' => 'alerta_parental',
                    'student_id' => $student->id,
                    'student_name' => $student->name ?? $student->nickname ?? 'Filho',
                    'days_inactive' => $days,
                    'message' => "Aviso familiar: seu educando não conclui lições há {$days} dias.",
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function notifySchoolAdmin($student, int $days): void
    {
        if (DB::getSchemaBuilder()->hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\StudentAbandonmentSchoolNotification',
                'notifiable_type' => 'App\\Models\\Tenant',
                'notifiable_id' => $student->tenant_id ?? (string) \Illuminate\Support\Str::uuid(),
                'data' => json_encode([
                    'level' => 'school_admin',
                    'status' => 'abandonou',
                    'student_id' => $student->id,
                    'student_name' => $student->name ?? $student->nickname ?? 'Estudante',
                    'days_inactive' => $days,
                    'message' => "Alerta de Abandono Escolar: Aluno sem registro de atividade há {$days} dias.",
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
