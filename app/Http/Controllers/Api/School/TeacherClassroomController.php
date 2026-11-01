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
            ->with(['tenant:id,name', 'teenStudents:users.id,users.name,users.email', 'childStudents:child_profiles.id,child_profiles.name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'grade_level'   => $c->grade_level,
                'year'          => $c->year,
                'invite_code'   => $c->invite_code,
                'student_count' => $c->teenStudents->count() + $c->childStudents->count(),
                'students'      => $c->teenStudents->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'display_name' => $u->name, 'email' => $u->email, 'type' => 'teen'])
                    ->concat($c->childStudents->map(fn ($ch) => ['id' => $ch->id, 'name' => $ch->name, 'display_name' => $ch->name, 'type' => 'child']))
                    ->values(),
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

        // Alunos adolescentes (tabela users): XP = soma do XP ganho nas lições aprovadas
        $teenStudents = $classroom->teenStudents()
            ->select('users.id', 'users.name', 'users.email')
            ->get()
            ->map(function ($s) {
                $xp = (int) UserProgress::where('user_id', $s->id)->sum('total_xp_earned');

                return [
                    'id'              => $s->id,
                    'name'            => $s->name,
                    'type'            => 'teen',
                    'total_xp'        => $xp,
                    'level'           => (int) (floor($xp / 1000) + 1),
                    'pending_reviews' => UserProgress::where('user_id', $s->id)->pendingReview()->count(),
                ];
            });

        // Alunos crianca (child_profiles)
        $childStudents = $classroom->childStudents()
            ->select('child_profiles.id', 'child_profiles.name', 'child_profiles.total_xp')
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'name'            => $c->name,
                'type'            => 'child',
                'total_xp'        => (int) ($c->total_xp ?? 0),
                'level'           => (int) (floor(($c->total_xp ?? 0) / 1000) + 1),
                'pending_reviews' => UserProgress::where('child_profile_id', $c->id)->pendingReview()->count(),
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
        $teenIds = $classroom->teenStudents()->pluck('users.id');

        // Recolhe IDs de crianças
        $childIds = $classroom->childStudents()->pluck('child_profiles.id');

        $queue = UserProgress::with(['learningObject:id,title,type', 'teen:id,name', 'child:id,name'])
            ->where('status', 'pending_review')
            ->where(function ($q) use ($teenIds, $childIds) {
                $q->whereIn('user_id', $teenIds)
                  ->orWhereIn('child_profile_id', $childIds);
            })
            ->orderBy('submitted_at')
            ->get()
            ->map(fn($p) => [
                'progress_id'     => $p->id,
                'student_name'    => $p->teen?->name ?? $p->child?->name,
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
        abort_unless($teacher->teacherCan('can_assign_missions'), 403, 'Sem permissão para atribuir missões.');
        $classroom = $this->getTeacherClassroom($teacher, $id);
        $lo        = LearningObject::findOrFail($request->learning_object_id);

        $teenIds  = $classroom->teenStudents()->pluck('users.id');
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
            ->whereIn('role', ['teen', 'yang'])
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
    /**
     * GET /api/v1/teacher/dashboard — painel do professor com dados reais:
     * turmas (progresso e missoes activas), fila de avaliacao e radar de alunos.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $classrooms = Classroom::whereHas('teachers', fn ($q) => $q->where('users.id', $teacher->id))->where('is_active', true)->get();

        $queue = [];
        $alerts = [];
        $classes = [];
        $studentIdsAll = 0;

        foreach ($classrooms as $c) {
            $teenIds = $c->teenStudents()->pluck('users.id');
            $childIds = $c->childStudents()->pluck('child_profiles.id');
            $studentIdsAll += $teenIds->count() + $childIds->count();

            $progress = fn () => UserProgress::query()->where(fn ($w) => $w->whereIn('user_id', $teenIds)->orWhereIn('child_profile_id', $childIds));
            $total = $progress()->count();

            $classes[] = [
                'id' => $c->id,
                'name' => $c->name,
                'progress' => $total > 0 ? (int) round($progress()->where('completed', true)->count() / $total * 100) : 0,
                'student_count' => $teenIds->count() + $childIds->count(),
                'active_missions' => $progress()->where('completed', false)->whereNotIn('status', ['rejeitado', 'rejected'])->count(),
            ];

            // Fila de avaliacao (mais antigas primeiro)
            $progress()->with(['learningObject:id,title,type,reward_xp,reward_coins', 'teen:id,name', 'child:id,name,avatar'])
                ->where('status', 'pending_review')->orderBy('submitted_at')->limit(20)->get()
                ->each(function ($p) use (&$queue, $c) {
                    $queue[] = [
                        'id' => $p->id,
                        'student_name' => $p->child?->name ?? $p->teen?->name ?? 'Aluno',
                        'student_avatar' => $p->child?->avatar ?: '👤',
                        'classroom' => $c->name,
                        'type' => ($p->submission_url || $p->submission_payload) ? 'photo' : 'quiz',
                        'mission_title' => $p->learningObject?->title ?? 'Missão',
                        'submitted_at' => $p->submitted_at?->toISOString(),
                        'reward' => (int) ($p->learningObject?->reward_coins ?? 0),
                        'reward_xp' => (int) ($p->learningObject?->reward_xp ?? 0),
                    ];
                });

            // Radar: inactivos ha 7+ dias, tentativas repetidas e conquistas recentes
            $rows = $progress()->with(['teen:id,name', 'child:id,name,avatar'])->get();
            foreach ($rows->groupBy(fn ($p) => $p->child_profile_id ?? $p->user_id) as $sid => $items) {
                $first = $items->first();
                $name = $first->child?->name ?? $first->teen?->name ?? 'Aluno';
                $avatar = $first->child?->avatar ?: '👤';
                $last = $items->max('updated_at');

                if ($last && $last->lt(now()->subDays(7))) {
                    $alerts[] = ['id' => "in-{$sid}", 'name' => $name, 'classroom' => $c->name, 'avatar' => $avatar, 'alert' => 'Inativo há ' . (int) $last->diffInDays(now()) . ' dias', 'type' => 'inactive'];
                }
                if ($items->contains(fn ($p) => ! $p->completed && (int) $p->attempts >= 3)) {
                    $alerts[] = ['id' => "rp-{$sid}", 'name' => $name, 'classroom' => $c->name, 'avatar' => $avatar, 'alert' => 'Repetiu uma missão 3 ou mais vezes', 'type' => 'repeated'];
                }
                $recentDone = $items->filter(fn ($p) => $p->completed && $p->updated_at && $p->updated_at->gte(now()->subDays(7)))->count();
                if ($recentDone >= 3) {
                    $alerts[] = ['id' => "ok-{$sid}", 'name' => $name, 'classroom' => $c->name, 'avatar' => $avatar, 'alert' => "Concluiu {$recentDone} missões esta semana", 'type' => 'success'];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'classes' => $classes,
                'queue' => $queue,
                'alerts' => array_slice($alerts, 0, 10),
                'total_students' => $studentIdsAll,
            ],
        ]);
    }

    /** GET /api/v1/teacher/student-progress — progresso pedagogico de cada aluno das turmas do professor. */
    public function studentProgressList(Request $request): JsonResponse
    {
        $rows = [];
        foreach ($this->teacherClassrooms($request->user()) as $c) {
            foreach ($c->childStudents()->get(['child_profiles.id', 'child_profiles.name', 'child_profiles.total_xp']) as $s) {
                $rows[$s->id] = $this->studentStats($s->id, null, (int) $s->total_xp);
            }
            foreach ($c->teenStudents()->get(['users.id', 'users.name']) as $s) {
                $rows[$s->id] = $this->studentStats(null, $s->id, (int) UserProgress::where('user_id', $s->id)->sum('total_xp_earned'));
            }
        }

        return response()->json(['status' => 'success', 'data' => collect($rows)->map(fn ($r, $id) => ['profile_id' => $id] + $r)->values()]);
    }

    /** GET /api/v1/teacher/milestones — marcos das turmas do professor. */
    public function milestonesList(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => ReportController::classroomMilestones($this->teacherClassrooms($request->user())),
        ]);
    }

    /** GET /api/v1/teacher/students/{id} — ficha pedagogica do aluno (sem saldos nem dados financeiros). */
    public function studentDetail(Request $request, string $id): JsonResponse
    {
        $classrooms = $this->teacherClassrooms($request->user());
        $classroom = $classrooms->first(
            fn (Classroom $c) => $c->childStudents()->where('child_profiles.id', $id)->exists() || $c->teenStudents()->where('users.id', $id)->exists()
        );
        abort_if(! $classroom, 404);

        $child = \App\Models\ChildProfile::find($id);
        $teen = $child ? null : User::find($id);
        $name = $child?->name ?? $teen?->name ?? 'Aluno';

        $progress = UserProgress::with('learningObject:id,title')
            ->where($child ? 'child_profile_id' : 'user_id', $id)->latest('updated_at')->get();
        $xp = $child ? (int) $child->total_xp : (int) $progress->sum('total_xp_earned');
        $stats = $this->studentStats($child?->id, $teen?->id, $xp);

        $vaults = $child ? \App\Models\DreamVault::where('child_profile_id', $id)->get() : collect();
        $badges = $child ? \App\Models\Badge::whereHas('children', fn ($q) => $q->where('child_profiles.id', $id))->get() : collect();

        return response()->json(['status' => 'success', 'data' => [
            'profile' => [
                'id' => $id,
                'display_name' => $name,
                'username' => $child?->username,
                'avatar' => $child?->avatar,
                'classroom' => $classroom->name,
            ],
            'pedagogical' => [
                'level' => $stats['level'],
                'coins_earned' => (int) $progress->sum('total_coins_earned'),
                'missions_completed' => $stats['missions_completed'],
                'completion_rate' => $stats['completion_rate'],
                'kiva_points' => $stats['kiva_points'],
            ],
            'tasks' => $progress->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->learningObject?->title ?? 'Missão',
                'status' => $p->completed ? 'completed' : ($p->status === 'pending_review' ? 'in_progress' : (($p->attempts ?? 0) > 0 ? 'in_progress' : 'available')),
                'reward' => (int) $p->total_coins_earned,
            ])->values(),
            // So o progresso das metas (percentagem), nunca montantes
            'vaults' => $vaults->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->name,
                'icon' => $v->icon,
                'target_amount' => 100,
                'current_amount' => (float) $v->target_amount > 0 ? min(100, round((float) $v->current_amount / (float) $v->target_amount * 100)) : 0,
            ])->values(),
            'badges' => $badges->map(fn ($b) => ['id' => $b->id, 'name' => $b->name ?? $b->title ?? 'Badge', 'icon' => $b->icon ?? '🏅', 'description' => $b->description ?? ''])->values(),
        ]]);
    }

    /** @return \Illuminate\Support\Collection<int,Classroom> */
    private function teacherClassrooms(User $teacher)
    {
        return Classroom::whereHas('teachers', fn ($q) => $q->where('users.id', $teacher->id))->where('is_active', true)->get();
    }

    private function studentStats(?string $childId, ?string $userId, int $xp): array
    {
        $q = fn () => UserProgress::query()->where($childId ? 'child_profile_id' : 'user_id', $childId ?? $userId);
        $total = $q()->count();
        $done = $q()->where('completed', true)->count();

        return [
            'completion_rate' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'missions_completed' => $done,
            'kiva_points' => $xp,
            'level' => intdiv($xp, 100) + 1,
        ];
    }

    private function getTeacherClassroom(User $teacher, string $classroomId, bool $withTenant = false): Classroom
    {
        $query = Classroom::whereHas('teachers', fn($q) => $q->where('users.id', $teacher->id));

        if ($withTenant) {
            $query->with('tenant:id,name');
        }

        return $query->findOrFail($classroomId);
    }
}
