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

class SchoolDashboardController extends Controller
{
    /**
     * GET /api/v1/school/dashboard
     * 
     * Retorna os KPIs principais, curvas de engajamento semanal e progresso por ano de escolaridade.
     */
        #[OA\Get(
        path: "/api/v1/school/dashboard",
        summary: "School Dashboard (GET)",
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
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilizador não associado a uma instituição escolar.',
            ], 403);
        }

        // 1. Contagens principais
        $totalStudents = ChildProfile::where('tenant_id', $tenantId)->count()
                       + User::where('tenant_id', $tenantId)->whereIn('role', ['teen', 'yang', 'student'])->count();

        $totalClassrooms = Classroom::where('tenant_id', $tenantId)->where('is_active', true)->count();

        $totalTeachers = User::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('role', 'teacher')
                  ->orWhereHas('roles', fn($r) => $r->where('name', 'teacher'));
            })->count();

        // 2. Missões ativas / submetidas hoje
        $activeMissionsToday = UserProgress::whereDate('updated_at', today())
            ->where(function ($q) use ($tenantId) {
                $q->whereHas('child', fn($c) => $c->where('tenant_id', $tenantId))
                  ->orWhereHas('teen', fn($t) => $t->where('tenant_id', $tenantId));
            })->count();

        // 3. Taxa de engajamento da escola
        $completedTotal = UserProgress::where('completed', true)
            ->where(function ($q) use ($tenantId) {
                $q->whereHas('child', fn($c) => $c->where('tenant_id', $tenantId))
                  ->orWhereHas('teen', fn($t) => $t->where('tenant_id', $tenantId));
            })->count();

        $allProgressTotal = UserProgress::where(function ($q) use ($tenantId) {
            $q->whereHas('child', fn($c) => $c->where('tenant_id', $tenantId))
              ->orWhereHas('teen', fn($t) => $t->where('tenant_id', $tenantId));
        })->count();

        $engagementRate = $allProgressTotal > 0
            ? round(($completedTotal / $allProgressTotal) * 100, 1)
            : 0.0;

        // 4. Curva de engajamento dos últimos 7 dias
        $daysMap = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        $engagementCurve = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayName = $daysMap[$date->dayOfWeekIso - 1] ?? 'Dia';

            $dayStats = UserProgress::whereDate('updated_at', $date->toDateString())
                ->where(function ($q) use ($tenantId) {
                    $q->whereHas('child', fn($c) => $c->where('tenant_id', $tenantId))
                      ->orWhereHas('teen', fn($t) => $t->where('tenant_id', $tenantId));
                })
                ->selectRaw("
                    COALESCE(SUM(total_xp_earned), 0) as xp,
                    COUNT(id) as missions
                ")->first();

            $engagementCurve[] = [
                'day'      => $dayName,
                'xp'       => (int) ($dayStats->xp ?? 0),
                'missions' => (int) ($dayStats->missions ?? 0),
            ];
        }

        // 5. Progresso por ano letivo / grau
        // Progresso real por ano/classe: % de licoes concluidas pelos alunos das turmas desse ano
        $progressByYear = Classroom::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id', 'grade_level'])
            ->groupBy(fn ($c) => $c->grade_level ?: 'Geral')
            ->map(function ($rooms, $year) {
                $members = \App\Models\ClassroomUser::query()->whereIn('classroom_id', $rooms->pluck('id'))->where('role', 'student')->get(['child_profile_id', 'user_id']);
                $childIds = $members->pluck('child_profile_id')->filter();
                $userIds = $members->pluck('user_id')->filter();
                $total = UserProgress::where(fn ($q) => $q->whereIn('child_profile_id', $childIds)->orWhereIn('user_id', $userIds))->count();
                $done = UserProgress::where('completed', true)->where(fn ($q) => $q->whereIn('child_profile_id', $childIds)->orWhereIn('user_id', $userIds))->count();

                return ['year' => (string) $year, 'progress' => $total > 0 ? (int) round($done / $total * 100) : 0];
            })->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'total_students'         => $totalStudents,
                    'total_classrooms'       => $totalClassrooms,
                    'total_teachers'         => $totalTeachers,
                    'active_missions_today'  => $activeMissionsToday,
                    'school_engagement_rate' => $engagementRate,
                    'status'                 => 'healthy',
                ],
                'engagement_curve'  => $engagementCurve,
                'progress_by_year'  => $progressByYear,
            ],
            // Compatibilidade com payload direto de summary no root
            'summary'          => [
                'total_students'         => $totalStudents,
                'total_classrooms'       => $totalClassrooms,
                'total_teachers'         => $totalTeachers,
                'active_missions_today'  => $activeMissionsToday,
                'school_engagement_rate' => $engagementRate,
                'status'                 => 'healthy',
            ],
            'engagement_curve' => $engagementCurve,
            'progress_by_year' => $progressByYear,
        ], 200);
    }
}
