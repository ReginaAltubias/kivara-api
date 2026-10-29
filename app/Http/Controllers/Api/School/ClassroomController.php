<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\ChildProfile;
use App\Models\Lesson;
use App\Models\UserProgress;
use App\Services\PedagogicalOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClassroomController extends Controller
{
    public function __construct(
        protected ?PedagogicalOrchestratorService $pedagogicalService = null
    ) {
        $this->pedagogicalService = $pedagogicalService ?? app(PedagogicalOrchestratorService::class);
    }

    /**
     * GET /api/v1/classrooms - Listar Turmas
     */
        #[OA\Get(
        path: "/api/v1/school/classrooms",
        summary: "Listar Turmas Escolares",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $classrooms = $this->scopedQuery($request)
            ->with('teacher')
            ->withCount('students as student_count')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $classrooms,
        ], 200);
    }

    /**
     * POST /api/v1/classrooms - Criar Turma
     */
        #[OA\Post(
        path: "/api/v1/school/classrooms",
        summary: "Criar Nova Turma Escolar",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'grade' => 'nullable|string',
            'grade_level' => 'nullable|string',
            'year' => 'nullable|string',
        ]);

        $classroom = Classroom::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->name,
            'grade' => $request->grade ?? $request->grade_level ?? '5-7',
            'grade_level' => $request->grade_level ?? $request->grade ?? '5-7',
            'year' => $request->year ?? date('Y') . '/' . (date('Y') + 1),
            'teacher_id' => $request->user()->id,
            'teacher_profile_id' => $request->user()->profile_id ?? $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Turma criada com sucesso.',
            'data' => $classroom,
        ], 201);
    }

    /**
     * GET /api/v1/classrooms/{id} - Ver Detalhe de Turma
     */
        #[OA\Get(
        path: "/api/v1/classrooms/{id}",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        return response()->json([
            'status' => 'success',
            'data' => $classroom->load(['teachers', 'students']),
        ], 200);
    }

    /**
     * PATCH /api/v1/classrooms/{id} - Atualizar Turma
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'grade' => 'sometimes|nullable|string|max:50',
            'grade_level' => 'sometimes|nullable|string|max:50',
            'year' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'teacher_id' => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        if (isset($data['grade']) && !isset($data['grade_level'])) {
            $data['grade_level'] = $data['grade'];
        }
        unset($data['grade']);

        $classroom->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Turma atualizada com sucesso.',
            'data' => $classroom->fresh(),
        ], 200);
    }

    /**
     * DELETE /api/v1/classrooms/{id} - Eliminar Turma (desvincula alunos, professores e lições)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        DB::transaction(function () use ($classroom) {
            ClassroomUser::where('classroom_id', $classroom->id)->delete();
            DB::table('classroom_lessons')->where('classroom_id', $classroom->id)->delete();
            $classroom->delete();
        });

        return response()->json(['status' => 'success', 'message' => 'Turma eliminada com sucesso.'], 200);
    }

    /**
     * GET /api/v1/classrooms/{id}/lessons - Listar lições atribuídas à turma
     */
        #[OA\Get(
        path: "/api/v1/teacher/classrooms/{id}/lessons",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function lessons(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        $assignedLessons = DB::table('classroom_lessons')
            ->where('classroom_id', $classroom->id)
            ->get()
            ->map(function ($cl) {
                $lesson = Lesson::find($cl->lesson_id);
                return [
                    'id' => $cl->id ?? (string) \Illuminate\Support\Str::uuid(),
                    'classroom_id' => $cl->classroom_id,
                    'lesson_id' => $cl->lesson_id,
                    'due_date' => $cl->due_date,
                    'priority' => $cl->priority ?? 'medium',
                    'assigned_at' => $cl->assigned_at ?? $cl->created_at ?? now()->toIso8601String(),
                    'lesson' => $lesson ? [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'icon' => $lesson->icon ?? '💰',
                        'category' => $lesson->category ?? 'saving',
                        'difficulty' => $lesson->difficulty ?? 'explorer',
                        'estimated_minutes' => $lesson->estimated_minutes ?? 15,
                        'kiva_points_reward' => $lesson->kiva_points_reward ?? $lesson->xp_reward ?? 50,
                        'mission_template_id' => $lesson->mission_template_id ?? optional($lesson->missionTemplates->first())->id,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $assignedLessons,
        ], 200);
    }

    /**
     * POST /api/v1/classrooms/{id}/lessons - Atribuir lição com validação de Loop 1
     */
        #[OA\Post(
        path: "/api/v1/teacher/classrooms/{id}/lessons",
        summary: "Criar Nova Turma Escolar",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function assignLesson(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'lesson_id' => 'required|uuid|exists:lessons,id',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $classroom = $this->findScoped($request, $id);
        $lesson = Lesson::with('missionTemplates')->findOrFail($request->lesson_id);

        try {
            $assignment = $this->pedagogicalService->assignLessonToClassroom(
                classroom: $classroom,
                lesson: $lesson,
                dueDate: $request->due_date,
                priority: $request->priority ?? 'medium'
            );

            return response()->json([
                'data' => $assignment,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/classrooms/{id}/lessons/{lessonId} - Desvincular lição
     */
        #[OA\Delete(
        path: "/api/v1/classrooms/{id}/lessons/{lessonId}",
        summary: "Classrooms {id} Lessons {lessonId} (DELETE)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function removeLesson(Request $request, string $id, string $lessonId): JsonResponse
    {
        $this->findScoped($request, $id);

        DB::table('classroom_lessons')
            ->where('classroom_id', $id)
            ->where('lesson_id', $lessonId)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lição desvinculada da turma com sucesso.',
        ], 200);
    }

    /**
     * DELETE /api/v1/lessons/progress/{studentProfileId}/{lessonId} - Reabertura de Conteúdo
     */
        #[OA\Delete(
        path: "/api/v1/lessons/progress/{studentProfileId}/{lessonId}",
        summary: "Lessons Progress {studentProfileId} {lessonId} (DELETE)",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function resetProgress(Request $request, string $studentProfileId, string $lessonId): JsonResponse
    {
        UserProgress::where(function ($q) use ($studentProfileId) {
            $q->where('child_profile_id', $studentProfileId)
              ->orWhere('user_id', $studentProfileId);
        })
        ->where(function ($q) use ($lessonId) {
            $q->where('learning_object_id', $lessonId)
              ->orWhere('lesson_id', $lessonId);
        })
        ->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/classrooms/{id}/stats - KPIs & Relatório da Turma
     */
        #[OA\Get(
        path: "/api/v1/school/stats",
        summary: "School Stats (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function stats(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        $studentIds = $classroom->students()->pluck('child_profiles.id')->unique()->values();

        $assigned = DB::table('classroom_lessons')->where('classroom_id', $classroom->id);
        $lessonsAssigned = (clone $assigned)->count();
        $overdue = (clone $assigned)->whereNotNull('due_date')->where('due_date', '<', now())->count();

        $progress = UserProgress::whereIn('child_profile_id', $studentIds);
        $lessonsTotal = $lessonsAssigned * $studentIds->count();
        $lessonsCompleted = (clone $progress)->where('completed', true)->count();
        $lessonsCompleted = $lessonsTotal > 0 ? min($lessonsCompleted, $lessonsTotal) : $lessonsCompleted;

        $withProgress = (clone $progress)->where('completed', true)->pluck('child_profile_id')->unique();
        $atRisk = $lessonsAssigned > 0 ? $studentIds->diff($withProgress)->count() : 0;

        return response()->json([
            'data' => [
                'classroom_id' => $classroom->id,
                'students_count' => $studentIds->count(),
                'completion_rate' => $lessonsTotal > 0 ? round($lessonsCompleted / $lessonsTotal, 2) : 0.0,
                'lessons_assigned' => $lessonsAssigned,
                'lessons_completed' => $lessonsCompleted,
                'lessons_total' => $lessonsTotal,
                'missions_completed' => $lessonsCompleted,
                'missions_overdue' => $overdue,
                'avg_kiva_points' => $studentIds->isEmpty() ? 0 : (int) round((clone $progress)->sum('total_xp_earned') / $studentIds->count()),
                'students_at_risk' => $atRisk,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/school/stats - KPIs agregados da escola do utilizador autenticado
     */
    public function schoolStats(Request $request): JsonResponse
    {
        $classroomIds = $this->scopedQuery($request)->pluck('id');

        $studentIds = ClassroomUser::whereIn('classroom_id', $classroomIds)
            ->where('role', 'student')->whereNotNull('child_profile_id')->pluck('child_profile_id')->unique();

        $assigned = DB::table('classroom_lessons')->whereIn('classroom_id', $classroomIds)->count();
        $completed = UserProgress::whereIn('child_profile_id', $studentIds)->where('completed', true)->count();

        $tenantId = $request->user()->tenant_id;
        $teachers = \App\Models\User::when($tenantId && $request->user()->role !== 'admin', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('role', ['teacher', 'coordinator'])->count();

        return response()->json([
            'data' => [
                'classrooms_count' => $classroomIds->count(),
                'students_count' => $studentIds->count(),
                'teachers_count' => $teachers,
                'lessons_assigned' => $assigned,
                'lessons_completed' => $completed,
                'completion_rate' => ($assigned * max(1, $studentIds->count())) > 0
                    ? round(min($completed / ($assigned * $studentIds->count()), 1), 2) : 0.0,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/classrooms/{id}/assign-teacher
     */
        #[OA\Post(
        path: "/api/v1/b2b/classrooms/{id}/assign-teacher",
        summary: "Atribuir Professor Responsável à Turma",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function assignTeacher(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $classroom = $this->findScoped($request, $id);

        $existing = ClassroomUser::where('classroom_id', $classroom->id)
            ->where('user_id', $request->user_id)
            ->where('role', 'teacher')
            ->first();

        if (!$existing) {
            ClassroomUser::create([
                'classroom_id' => $classroom->id,
                'user_id' => $request->user_id,
                'role' => 'teacher',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Professor associado à turma com sucesso.',
        ], 200);
    }

    /**
     * GET /api/v1/classrooms/{id}/students - Listar Alunos
     */
        #[OA\Get(
        path: "/api/v1/b2b/classrooms/{id}/students",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function students(Request $request, string $id): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);
        $students = $classroom->students()->get();

        return response()->json([
            'status' => 'success',
            'data' => $students,
        ], 200);
    }

    /**
     * POST /api/v1/classrooms/{id}/students - Adicionar Alunos
     */
        #[OA\Post(
        path: "/api/v1/b2b/classrooms/{id}/students",
        summary: "Adicionar Alunos à Turma",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function addStudents(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'uuid|exists:child_profiles,id',
        ]);

        $classroom = $this->findScoped($request, $id);

        $added = 0;
        foreach ($request->student_ids as $studentId) {
            $existing = ClassroomUser::where('classroom_id', $classroom->id)
                ->where('child_profile_id', $studentId)
                ->where('role', 'student')
                ->first();

            if (!$existing) {
                ClassroomUser::create([
                    'classroom_id' => $classroom->id,
                    'child_profile_id' => $studentId,
                    'role' => 'student',
                ]);
                $added++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Adicionados $added aluno(s)",
            'data' => [
                'added_count' => $added,
                'classroom' => [
                    'id' => $classroom->id,
                    'student_count' => $classroom->students()->count(),
                ],
            ],
        ], 200);
    }

    /**
     * DELETE /api/v1/classrooms/{id}/students/{studentId} - Remover Aluno
     */
        #[OA\Delete(
        path: "/api/v1/b2b/classrooms/{id}/students/{studentId}",
        summary: "B2b Classrooms {id} Students {studentId} (DELETE)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function removeStudent(Request $request, string $id, string $studentId): JsonResponse
    {
        $classroom = $this->findScoped($request, $id);

        ClassroomUser::where('classroom_id', $classroom->id)
            ->where('child_profile_id', $studentId)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Aluno removido.',
        ], 200);
    }

    /** Turmas visiveis ao utilizador: todas para admin, caso contrario so as do seu tenant. */
    private function scopedQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        $query = Classroom::query();

        if ($user->role !== 'admin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    private function findScoped(Request $request, string $id): Classroom
    {
        return $this->scopedQuery($request)->findOrFail($id);
    }
}
