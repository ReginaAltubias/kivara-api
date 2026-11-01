<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\UserProgress;
use Illuminate\Support\Facades\DB;

/**
 * Metricas de impacto do parceiro, calculadas a partir das turmas inscritas nos seus desafios
 * (agregados, sem dados pessoais). Sem dados => zeros.
 */
class PartnerImpactService
{
    public function calculatePartnerMetrics(Partner $partner, ?string $programId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $programs = $partner->programs()->when($programId, fn ($q) => $q->where('id', $programId))->get();
        $challenges = PartnerChallenge::where('partner_id', $partner->id)
            ->when($programId, fn ($q) => $q->where('program_id', $programId))->get();

        $enrol = $this->enrolments($challenges->pluck('id'), $startDate, $endDate);
        $students = $this->studentsOf($enrol->pluck('classroom_id')->unique());
        $progress = $this->progressOf($students);

        $total = (clone $progress)->count();
        $done = (clone $progress)->where('completed', true)->count();
        $rejected = (clone $progress)->whereIn('status', ['rejeitado', 'rejected'])->count();
        $completionRate = $total > 0 ? round($done / $total * 100, 1) : 0.0;

        $allocated = (float) $programs->sum('allocated_budget');
        $committed = (float) $programs->sum(fn ($p) => $p->committedBudget());
        $disbursed = min($committed, $allocated);

        $schools = DB::table('classrooms')->whereIn('id', $enrol->pluck('classroom_id')->unique())->distinct()->pluck('tenant_id')->filter()->count();

        $programsSummary = $programs->map(function ($p) use ($startDate, $endDate) {
            $ids = PartnerChallenge::where('program_id', $p->id)->pluck('id');
            $rows = $this->enrolments($ids, $startDate, $endDate);
            $stu = $this->studentsOf($rows->pluck('classroom_id')->unique());
            $prog = $this->progressOf($stu);
            $t = (clone $prog)->count();

            return [
                'program_id' => $p->id,
                'title' => $p->title,
                'students_count' => $stu['child']->count() + $stu['user']->count(),
                'completion_rate' => $t > 0 ? round((clone $prog)->where('completed', true)->count() / $t, 2) : 0,
            ];
        })->values()->toArray();

        return [
            'total_students_reached' => $students['child']->count() + $students['user']->count(),
            'total_schools_participating' => $schools,
            'challenges_completed' => $enrol->where('status', 'completed')->count(),
            'total_budget_allocated' => $allocated,
            'total_budget_disbursed' => $disbursed,
            'active_programs_count' => $programs->where('status', 'active')->count(),
            // indice 0-10 = taxa de conclusao dos alunos alcancados (0 sem dados)
            'impact_index_score' => round($completionRate / 10, 1),
            'average_completion_rate' => $completionRate,
            'total_missions_completed' => $done,
            'progress_breakdown' => ['completed' => $done, 'in_progress' => max($total - $done - $rejected, 0), 'rejected' => $rejected],
            'programs_summary' => $programsSummary,
        ];
    }

    /** Evolucao mensal (ultimos 6 meses): alunos alcancados, missoes concluidas e XP. */
    public function timeSeries(Partner $partner): array
    {
        $challengeIds = PartnerChallenge::where('partner_id', $partner->id)->pluck('id');
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $enrol = DB::table('classroom_challenge')->whereIn('partner_challenge_id', $challengeIds)->where('created_at', '<=', $end)->get(['classroom_id']);
            $students = $this->studentsOf($enrol->pluck('classroom_id')->unique());
            $month = $this->progressOf($students)->whereBetween('updated_at', [$start, $end]);

            $series[] = [
                'month' => mb_convert_case($start->locale('pt')->translatedFormat('M'), MB_CASE_TITLE),
                'students' => $students['child']->count() + $students['user']->count(),
                'missions' => (clone $month)->where('completed', true)->count(),
                'xp' => (int) (clone $month)->sum('total_xp_earned'),
            ];
        }

        return $series;
    }

    /** Distribuicao geografica por provincia da escola (agregada). */
    public function geoDistribution(Partner $partner): array
    {
        $challengeIds = PartnerChallenge::where('partner_id', $partner->id)->pluck('id');
        $classrooms = DB::table('classroom_challenge')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_challenge.classroom_id')
            ->leftJoin('tenants', 'tenants.id', '=', 'classrooms.tenant_id')
            ->whereIn('classroom_challenge.partner_challenge_id', $challengeIds)
            ->select('classrooms.id as classroom_id', 'classrooms.tenant_id', 'tenants.province')
            ->distinct()->get();

        $rows = [];
        foreach ($classrooms->groupBy(fn ($c) => $c->province ?: 'Sem província') as $province => $items) {
            $students = $this->studentsOf($items->pluck('classroom_id')->unique());
            $rows[] = [
                'province' => $province,
                'students' => $students['child']->count() + $students['user']->count(),
                'schools' => $items->pluck('tenant_id')->filter()->unique()->count(),
            ];
        }

        $total = max(array_sum(array_column($rows, 'students')), 1);
        $palette = ['#187558', '#122f54', '#b68933', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6'];
        usort($rows, fn ($a, $b) => $b['students'] <=> $a['students']);

        return array_map(fn ($r, $i) => $r + ['percentage' => (int) round($r['students'] / $total * 100), 'color' => $palette[$i % count($palette)]], $rows, array_keys($rows));
    }

    /** Alunos (criancas e adolescentes) das turmas dadas. */
    public function studentsOf($classroomIds): array
    {
        $rows = \App\Models\ClassroomUser::whereIn('classroom_id', $classroomIds)->where('role', 'student')->get(['child_profile_id', 'user_id']);

        return ['child' => $rows->pluck('child_profile_id')->filter()->unique()->values(), 'user' => $rows->pluck('user_id')->filter()->unique()->values()];
    }

    private function progressOf(array $students)
    {
        return UserProgress::query()->where(fn ($w) => $w->whereIn('child_profile_id', $students['child'])->orWhereIn('user_id', $students['user']));
    }

    private function enrolments($challengeIds, ?string $start, ?string $end)
    {
        return DB::table('classroom_challenge')->whereIn('partner_challenge_id', $challengeIds)
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
            ->get(['classroom_id', 'status', 'current_progress']);
    }
}
