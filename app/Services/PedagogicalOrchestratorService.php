<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Trail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PedagogicalOrchestratorService
{
    /**
     * Valida a Regra de Ouro do Loop 1:
     * Nenhuma aula pode ser aprovada ou atribuída a turmas sem possuir uma missão prática vinculada.
     */
    public function validateLoop1Requirement(Lesson $lesson): bool
    {
        if (!empty($lesson->mission_template_id)) {
            return true;
        }

        if ($lesson->relationLoaded('missionTemplates')) {
            return $lesson->missionTemplates->isNotEmpty();
        }

        return $lesson->missionTemplates()->exists();
    }

    /**
     * Publica uma Trilha, tornando-a ativa e imutável para edições diretas.
     */
    public function publishTrail(Trail $trail): Trail
    {
        // Valida se todas as lições da trilha satisfazem o Loop 1
        $lessons = $trail->lessons;
        foreach ($lessons as $lesson) {
            if (!$this->validateLoop1Requirement($lesson)) {
                throw new InvalidArgumentException(
                    "Não é possível publicar a trilha. A lição '{$lesson->title}' não possui missão prática associada (Loop 1 obrigatório)."
                );
            }
        }

        $trail->is_published = true;
        $trail->status = ContentStatus::Published ?? 'published';
        $trail->save();

        return $trail;
    }

    /**
     * Cria uma nova versão imutável de uma trilha publicada para permitir alterações pedagógicas.
     */
    public function createNewTrailVersion(Trail $trail): Trail
    {
        return DB::transaction(function () use ($trail) {
            $currentVersion = $trail->version ?? 1;
            $newVersionNumber = $currentVersion + 1;

            $newTrail = $trail->replicate([
                'id',
                'created_at',
                'updated_at',
            ]);

            $newTrail->id = (string) Str::uuid();
            $newTrail->version = $newVersionNumber;
            $newTrail->parent_trail_id = $trail->id;
            $newTrail->is_published = false;
            $newTrail->status = ContentStatus::Draft ?? 'draft';
            $newTrail->save();

            // Clona as lições associadas
            foreach ($trail->lessons as $lesson) {
                $newLesson = $lesson->replicate([
                    'id',
                    'created_at',
                    'updated_at',
                ]);
                $newLesson->id = (string) Str::uuid();
                $newLesson->trail_id = $newTrail->id;
                $newLesson->status = ContentStatus::Draft ?? 'draft';
                $newLesson->save();
            }

            return $newTrail->load('lessons');
        });
    }

    /**
     * Atribui uma lição a uma turma escolar B2B com prazo e prioridade.
     */
    public function assignLessonToClassroom(
        Classroom $classroom,
        Lesson $lesson,
        ?string $dueDate = null,
        string $priority = 'medium'
    ): array {
        if (!$this->validateLoop1Requirement($lesson)) {
            throw new InvalidArgumentException(
                "A lição '{$lesson->title}' não possui missão associada (mission_template_id ausente) e não pode ser atribuída à turma."
            );
        }

        $id = (string) Str::uuid();
        $assignedAt = now();

        // Insere ou atualiza na tabela pivô classroom_lessons
        DB::table('classroom_lessons')->updateOrInsert(
            [
                'classroom_id' => $classroom->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'id' => $id,
                'due_date' => $dueDate ? date('Y-m-d H:i:s', strtotime($dueDate)) : null,
                'priority' => $priority,
                'assigned_at' => $assignedAt,
                'updated_at' => now(),
            ]
        );

        return [
            'id' => $id,
            'classroom_id' => $classroom->id,
            'lesson_id' => $lesson->id,
            'due_date' => $dueDate,
            'priority' => $priority,
            'assigned_at' => $assignedAt->toIso8601String(),
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'icon' => $lesson->icon ?? '💰',
                'category' => $lesson->category ?? 'saving',
                'difficulty' => $lesson->difficulty ?? 'explorer',
                'estimated_minutes' => $lesson->estimated_minutes ?? 15,
                'kiva_points_reward' => $lesson->kiva_points_reward ?? 50,
                'mission_template_id' => $lesson->mission_template_id ?? optional($lesson->missionTemplates->first())->id,
            ],
        ];
    }
}
