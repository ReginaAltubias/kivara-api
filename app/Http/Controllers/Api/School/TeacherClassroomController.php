<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\LearningObject;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserProgress;
use App\Mail\StudentClassroomInvitationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TeacherClassroomController extends Controller
{
    // =========================================================================
    // GET /api/v1/b2b/teacher/classrooms
    // Lista as turmas às quais o professor pertence (como teacher)
    // =========================================================================
        #[OA\Get(
        path: "/api/v1/b2b/teacher/classrooms",
        summary: "Listar Turmas Escolares",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function myClassrooms(Request $request): JsonResponse
    {
        $user = $request->user();

        $classrooms = Classroom::whereHas('teachers', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
            ->withCount([
                'pivot as student_count' => function ($q) {
                    $q->where('role', 'student');
                },
            ])
            ->with('tenant:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'grade_level'   => $c->grade_level,
                'year'          => $c->year,
                'invite_code'   => $c->invite_code,
                'student_count' => $c->student_count,
                'school'        => $c->tenant?->name,
            ]);

        return response()->json(['status' => 'success', 'data' => $classrooms]);
    }

    // =========================================================================
    // GET /api/v1/b2b/teacher/classrooms/{id}/progress
    // Lista alunos da turma com o seu progresso e XP
    // =========================================================================
        #[OA\Get(
        path: "/api/v1/b2b/teacher/classrooms/{id}/progress",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function studentsProgress(Request $request, string $id): JsonResponse
    {
        $teacher   = $request->user();
        $classroom = $this->getTeacherClassroom($teacher, $id);

        // Alunos teen (tabela users) — já associados via classroom_user
        $teenStudents = $classroom->students()   // scope de teens
            ->select('users.id', 'users.name', 'users.email')
            ->withSum('ledgerEntries as total_xp', 'amount')
            ->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'type'       => 'teen',
                'total_xp'   => (int) ($s->total_xp ?? 0),
                'level'      => (int) (floor(($s->total_xp ?? 0) / 1000) + 1),
                'pending_reviews' => UserProgress::where('user_id', $s->id)
                    ->pendingReview()->count(),
            ]);

        // Alunos criança (child_profiles)
        $childStudents = $classroom->childStudents()
            ->select('child_profiles.id', 'child_profiles.display_name')
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->display_name,
                'type'       => 'child',
                'total_xp'   => 0,
                'level'      => 1,
                'pending_reviews' => UserProgress::where('child_profile_id', $c->id)
                    ->pendingReview()->count(),
            ]);

        $all = $teenStudents->merge($childStudents)->sortByDesc('total_xp')->values();

        return response()->json(['status' => 'success', 'data' => $all]);
    }

    // =========================================================================
    // GET /api/v1/b2b/teacher/classrooms/{id}/evaluations
    // Fila de avaliação: submissões pendentes de aprovação
    // =========================================================================
        #[OA\Get(
        path: "/api/v1/b2b/teacher/classrooms/{id}/evaluations",
        summary: "Obter Detalhes da Turma Escolar",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function evaluationQueue(Request $request, string $id): JsonResponse
    {
        $teacher   = $request->user();
        $classroom = $this->getTeacherClassroom($teacher, $id);

        // Recolhe IDs de alunos da turma (teens)
        $teenIds = $classroom->students()->pluck('users.id');

        // Recolhe IDs de crianças
        $childIds = $classroom->childStudents()->pluck('child_profiles.id');

        $queue = UserProgress::with(['learningObject:id,title,type', 'teen:id,name', 'child:id,display_name'])
            ->where('status', 'pending_review')
            ->where(function ($q) use ($teenIds, $childIds) {
                $q->whereIn('user_id', $teenIds)
                  ->orWhereIn('child_profile_id', $childIds);
            })
            ->orderBy('submitted_at')
            ->get()
            ->map(fn($p) => [
                'progress_id'     => $p->id,
                'student_name'    => $p->teen?->name ?? $p->child?->display_name,
                'student_type'    => $p->user_id ? 'teen' : 'child',
                'lesson_title'    => $p->learningObject?->title,
                'lesson_type'     => $p->learningObject?->type,
                'submission_url'  => $p->submission_url,
                'submission_note' => $p->submission_note,
                'submitted_at'    => $p->submitted_at?->toISOString(),
            ]);

        return response()->json(['status' => 'success', 'data' => $queue]);
    }

    // =========================================================================
    // POST /api/v1/b2b/teacher/classrooms/{id}/missions
    // Atribui uma lição/missão a todos os alunos da turma
    // =========================================================================
        #[OA\Post(
        path: "/api/v1/b2b/teacher/classrooms/{id}/missions",
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
    public function assignMission(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'learning_object_id' => 'required|uuid|exists:learning_objects,id',
        ]);

        $teacher   = $request->user();
        $classroom = $this->getTeacherClassroom($teacher, $id);
        $lo        = LearningObject::findOrFail($request->learning_object_id);

        $teenIds  = $classroom->students()->pluck('users.id');
        $childIds = $classroom->childStudents()->pluck('child_profiles.id');

        $assigned = 0;

        DB::transaction(function () use ($lo, $teenIds, $childIds, &$assigned) {
            foreach ($teenIds as $userId) {
                UserProgress::firstOrCreate(
                    ['user_id' => $userId, 'learning_object_id' => $lo->id],
                    ['status' => 'not_started', 'child_profile_id' => null]
                );
                $assigned++;
            }
            foreach ($childIds as $childId) {
                UserProgress::firstOrCreate(
                    ['child_profile_id' => $childId, 'learning_object_id' => $lo->id],
                    ['status' => 'not_started']
                );
                $assigned++;
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => "Missão \"{$lo->title}\" atribuída a {$assigned} aluno(s).",
        ]);
    }

    // =========================================================================
    // POST /api/v1/b2b/teacher/classrooms/{id}/invite-student
    // Convida um aluno (adolescente) por email
    // =========================================================================
        #[OA\Post(
        path: "/api/v1/b2b/teacher/classrooms/{id}/invite-student",
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
    public function inviteStudent(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $teacher   = $request->user();
        $classroom = $this->getTeacherClassroom($teacher, $id, withTenant: true);
        $schoolName = $classroom->tenant?->name ?? 'KIVARA';

        $student = User::where('email', $request->email)
            ->whereIn('name', ['teen', 'yang']) // role-based (spatie)
            ->first();

        // Aluno ainda não tem conta — envia convite por email
        if (!$student) {
            Mail::to($request->email)->send(new StudentClassroomInvitationMail(
                $classroom->name,
                $teacher->name,
                $schoolName,
                $classroom->invite_code,
            ));

            return response()->json([
                'status'  => 'success',
                'message' => 'Convite enviado por e-mail com sucesso.',
            ]);
        }

        // Aluno já existe — associa directamente
        DB::transaction(function () use ($student, $classroom) {
            if ($student->tenant_id !== $classroom->tenant_id) {
                $student->update(['tenant_id' => $classroom->tenant_id]);
            }

            $exists = ClassroomUser::where('classroom_id', $classroom->id)
                ->where('user_id', $student->id)
                ->where('role', 'student')
                ->exists();

            if (!$exists) {
                ClassroomUser::create([
                    'classroom_id' => $classroom->id,
                    'user_id'      => $student->id,
                    'role'         => 'student',
                ]);
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Aluno associado à turma com sucesso.',
        ]);
    }

    // =========================================================================
    // Helper: garante que a turma pertence ao professor autenticado
    // =========================================================================
    private function getTeacherClassroom(User $teacher, string $classroomId, bool $withTenant = false): Classroom
    {
        $query = Classroom::whereHas('teachers', fn($q) => $q->where('users.id', $teacher->id));

        if ($withTenant) {
            $query->with('tenant:id,name');
        }

        return $query->findOrFail($classroomId);
    }
}
