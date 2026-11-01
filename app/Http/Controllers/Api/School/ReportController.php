<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\Classroom;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    /**
     * GET /api/v1/school/reports
     * 
     * Relatório consolidado de ROI e Consistência Pedagógica (SchoolReports.tsx).
     */
        #[OA\Get(
        path: "/api/v1/school/reports",
        summary: "School Reports (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function reports(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'O utilizador autenticado não está associado a nenhuma escola.',
            ], 403);
        }

        $childIds = ChildProfile::where('tenant_id', $tenantId)->pluck('id');
        $teenIds = User::where('tenant_id', $tenantId)->whereIn('role', ['teen', 'yang', 'student'])->pluck('id');
        $activeStudentsCount = $childIds->count() + $teenIds->count();

        $tenantProgress = fn () => UserProgress::query()->where(
            fn ($q) => $q->whereIn('child_profile_id', $childIds)->orWhereIn('user_id', $teenIds)
        );

        $totalProgress = $tenantProgress()->count();
        $completedMissions = $tenantProgress()->where('completed', true)->count();
        $avgScore = $tenantProgress()->whereNotNull('best_score')->avg('best_score');
        $savings = (float) \App\Models\Wallet::where('walletable_type', ChildProfile::class)
            ->whereIn('walletable_id', $childIds)->sum('balance');

        // 1. Leaderboard de Professores
        $teachers = User::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('role', 'teacher')
                      ->orWhereHas('roles', fn($r) => $r->where('name', 'teacher'));
            })
            ->withCount('classrooms')
            ->withCount([
                'reviewedProgress as missions_evaluated' => function ($query) {
                    $query->whereNotNull('reviewed_at');
                }
            ])
            ->with('classrooms:id')
            ->orderByDesc('missions_evaluated')
            ->get()
            ->map(function ($t) {
                $classIds = $t->classrooms->pluck('id');
                $rows = \App\Models\ClassroomUser::query()->whereIn('classroom_id', $classIds)->where('role', 'student')->get(['child_profile_id', 'user_id']);
                $active = $rows->map(fn ($r) => $r->child_profile_id ?? $r->user_id)->filter()->unique()->count();

                $reviewed = UserProgress::where('reviewed_by', $t->id)->whereNotNull('reviewed_at')->whereNotNull('submitted_at')->get(['submitted_at', 'reviewed_at']);
                $avgMinutes = $reviewed->isEmpty() ? null : $reviewed->avg(fn ($p) => $p->submitted_at->diffInMinutes($p->reviewed_at));

                return [
                    'teacher_id'         => $t->id,
                    'teacher_name'       => $t->name,
                    'avatar'             => '👩🏫',
                    'classroom_count'    => (int) $t->classrooms_count,
                    'missions_evaluated' => (int) $t->missions_evaluated,
                    'avg_approval_time'  => $avgMinutes === null ? '—' : ($avgMinutes < 60 ? round($avgMinutes) . ' min' : round($avgMinutes / 60, 1) . ' h'),
                    'active_students'    => $active,
                ];
            });

        // 2. Progresso por turma
        $classProgress = Classroom::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($c) {
                $rows = \App\Models\ClassroomUser::query()->where('classroom_id', $c->id)->where('role', 'student')->get(['child_profile_id', 'user_id']);
                $childs = $rows->pluck('child_profile_id')->filter();
                $users = $rows->pluck('user_id')->filter();
                $q = fn () => UserProgress::query()->where(fn ($w) => $w->whereIn('child_profile_id', $childs)->orWhereIn('user_id', $users));
                $total = $q()->count();

                $done = $q()->where('completed', true)->count();
                $studentCount = $childs->count() + $users->count();
                $since = now()->subDays(30);
                $activeIds = $q()->where('completed', true)->where('updated_at', '>=', $since)->get(['child_profile_id', 'user_id'])
                    ->map(fn ($r) => $r->child_profile_id ?? $r->user_id)->unique()->count();

                return [
                    'classroom_id' => $c->id,
                    'name'         => $c->name,
                    'grade'        => $c->grade_level ?: 'Geral',
                    'progress'     => $total > 0 ? (int) round($done / $total * 100) : 0,
                    'xp'           => (int) $q()->sum('total_xp_earned'),
                    'lessons_completed' => $done,
                    'lessons_total'     => $total,
                    'missions_completed' => $done,
                    'missions_overdue'  => $q()->where('status', 'pending_review')->where('submitted_at', '<', now()->subDays(7))->count(),
                    'avg_kiva_points'   => $studentCount > 0 ? (int) round($q()->sum('total_xp_earned') / $studentCount) : 0,
                    'students_at_risk'  => max($studentCount - $activeIds, 0),
                ];
            });

        // 3. Curva de crescimento: ultimos 6 meses
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $monthRows = $tenantProgress()->whereBetween('updated_at', [$start, $end]);
            $months[] = [
                'month'    => mb_convert_case($start->locale('pt')->translatedFormat('M'), MB_CASE_TITLE),
                'xp'       => (int) (clone $monthRows)->sum('total_xp_earned'),
                'missions' => (clone $monthRows)->where('completed', true)->count(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'summary' => [
                'active_students'          => $activeStudentsCount,
                'avg_financial_score'      => round((float) $avgScore, 1),
                'completion_rate'          => $totalProgress > 0 ? round($completedMissions / $totalProgress * 100, 1) : 0,
                'collective_savings_aoa'   => $savings,
                'total_missions_completed' => $completedMissions,
            ],
            'growth_curve'        => $months,
            'teacher_leaderboard' => $teachers,
            'class_progress'      => $classProgress,
        ], 200);
    }

    /**
     * GET /api/v1/school/teacher-activity
     */
        #[OA\Get(
        path: "/api/v1/school/teacher-activity",
        summary: "School Teacher Activity (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function teacherActivity(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'O utilizador autenticado não está associado a nenhuma escola.',
            ], 403);
        }

        $thirtyDaysAgo = now()->subDays(30);

        $teachers = User::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('role', 'teacher')
                      ->orWhereHas('roles', fn($r) => $r->where('name', 'teacher'));
            })
            ->withCount('classrooms')
            ->withCount([
                'reviewedProgress as missions_evaluated_count' => function ($query) use ($thirtyDaysAgo) {
                    $query->whereNotNull('reviewed_at')
                          ->where('reviewed_at', '>=', $thirtyDaysAgo);
                }
            ])
            ->with('classrooms:id,name')
            ->orderByDesc('missions_evaluated_count')
            ->get()
            ->map(function ($teacher) {
                $classIds = $teacher->classrooms->pluck('id');
                $rows = \App\Models\ClassroomUser::query()->whereIn('classroom_id', $classIds)->where('role', 'student')->get(['child_profile_id', 'user_id']);
                $childs = $rows->pluck('child_profile_id')->filter();
                $users = $rows->pluck('user_id')->filter();
                $q = fn () => UserProgress::query()->where(fn ($w) => $w->whereIn('child_profile_id', $childs)->orWhereIn('user_id', $users));
                $total = $q()->count();
                $lastUsed = \Illuminate\Support\Facades\DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)->where('tokenable_id', $teacher->id)->max('last_used_at');

                return [
                    'id'                       => $teacher->id,
                    'name'                     => $teacher->name,
                    'display_name'             => $teacher->name,
                    'email'                    => $teacher->email,
                    'classrooms_count'         => (int) $teacher->classrooms_count,
                    'classrooms_names'         => $teacher->classrooms->pluck('name')->values(),
                    'missions_evaluated_count' => (int) $teacher->missions_evaluated_count,
                    'missions_evaluated'       => (int) $teacher->missions_evaluated_count,
                    'lessons_launched'         => (int) \Illuminate\Support\Facades\DB::table('classroom_lessons')->whereIn('classroom_id', $classIds)->count(),
                    'avg_student_completion'   => $total > 0 ? (int) round($q()->where('completed', true)->count() / $total * 100) : 0,
                    'xp_generated'             => (int) $q()->sum('total_xp_earned'),
                    'last_active'              => $lastUsed ? \Carbon\Carbon::parse($lastUsed)->toISOString() : null,
                    'is_active'                => (bool) $teacher->is_active,
                    'created_at'               => $teacher->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $teachers,
        ], 200);
    }

    /**
     * GET /api/v1/school/milestones
     */
        #[OA\Get(
        path: "/api/v1/school/milestones",
        summary: "School Milestones (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function milestones(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'O utilizador autenticado não está associado a nenhuma escola.',
            ], 403);
        }

        $cacheKey = 'milestones_tenant_' . $tenantId;

        $milestonesData = Cache::remember($cacheKey, now()->addHours(6), function () use ($tenantId) {
            $teenStudentIds = User::where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->whereIn('role', ['teen', 'yang', 'student'])
                      ->orWhereHas('roles', fn($r) => $r->whereIn('name', ['teen', 'yang', 'student']));
                })
                ->pluck('id');

            $childStudentIds = ChildProfile::where('tenant_id', $tenantId)
                ->pluck('id');

            $activeStudentsCount = $teenStudentIds->count() + $childStudentIds->count();

            $progressStats = UserProgress::where(function ($query) use ($teenStudentIds, $childStudentIds) {
                $query->whereIn('user_id', $teenStudentIds)
                      ->orWhereIn('child_profile_id', $childStudentIds);
            })->selectRaw("
                COALESCE(SUM(CASE WHEN COALESCE(total_xp_earned,0) >= COALESCE(xp_awarded,0) THEN COALESCE(total_xp_earned,0) ELSE COALESCE(xp_awarded,0) END), 0) as total_xp,
                COALESCE(SUM(CASE WHEN COALESCE(total_coins_earned,0) >= COALESCE(coins_awarded,0) THEN COALESCE(total_coins_earned,0) ELSE COALESCE(coins_awarded,0) END), 0) as total_coins,
                COUNT(CASE WHEN completed = true OR status = 'approved' THEN 1 END) as completed_missions,
                COUNT(CASE WHEN status = 'pending_review' THEN 1 END) as pending_evaluations
            ")->first();

            $totalClassrooms = Classroom::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            return [
                'active_students_count'     => (int) $activeStudentsCount,
                'total_xp_generated'        => (int) ($progressStats->total_xp ?? 0),
                'completed_missions_count'  => (int) ($progressStats->completed_missions ?? 0),
                'total_coins_generated'     => (int) ($progressStats->total_coins ?? 0),
                'pending_evaluations_count' => (int) ($progressStats->pending_evaluations ?? 0),
                'total_classrooms_count'    => (int) $totalClassrooms,
                'cached_at'                 => now()->toISOString(),
            ];
        });

        $classrooms = Classroom::where('tenant_id', $tenantId)->where('is_active', true)->get();

        return response()->json([
            'status'  => 'success',
            'data'    => self::classroomMilestones($classrooms),
            'summary' => $milestonesData,
        ], 200);
    }

    /**
     * Marcos pedagogicos das turmas (lista), calculados a partir do progresso real.
     *
     * @param  iterable<Classroom>  $classrooms
     */
    public static function classroomMilestones(iterable $classrooms): array
    {
        $out = [];
        foreach ($classrooms as $c) {
            $teenIds = $c->teenStudents()->pluck('users.id');
            $childIds = $c->childStudents()->pluck('child_profiles.id');
            $q = fn () => UserProgress::query()->where(fn ($w) => $w->whereIn('user_id', $teenIds)->orWhereIn('child_profile_id', $childIds));
            $total = $q()->count();
            $done = $q()->where('completed', true)->count();
            if ($total === 0) {
                continue;
            }
            $rate = (int) round($done / $total * 100);
            $stamp = ($q()->where('completed', true)->max('updated_at') ?? now())->toISOString();

            if ($rate >= 80) {
                $out[] = ['id' => "{$c->id}-completion", 'classroom_name' => $c->name, 'message' => "A turma atingiu {$rate}% de conclusão das missões.", 'type' => 'completion', 'created_at' => $stamp];
            }
            if ($done >= 10) {
                $out[] = ['id' => "{$c->id}-missions", 'classroom_name' => $c->name, 'message' => "A turma já concluiu {$done} missões.", 'type' => 'missions', 'created_at' => $stamp];
            }
        }

        return $out;
    }
}
