<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Services\PartnerImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Relatorios de impacto do parceiro: agregados reais, sem dados pessoais. */
class PartnerReportController extends Controller
{
    public function __construct(private readonly PartnerImpactService $impact)
    {
    }

    /** GET /api/v1/partner/reports/metrics */
    public function metrics(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $metrics = $this->impact->calculatePartnerMetrics(
            $partner,
            $request->query('program_id'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        return response()->json([
            'data' => array_merge($metrics, [
                'total_students' => $metrics['total_students_reached'],
                'total_schools' => $metrics['total_schools_participating'],
                'total_kvc_distributed' => $metrics['total_budget_disbursed'],
                'avg_completion_rate' => $metrics['average_completion_rate'],
                'total_students_impacted' => $metrics['total_students_reached'],
                'total_schools_impacted' => $metrics['total_schools_participating'],
                'total_coins_distributed' => $metrics['total_budget_disbursed'],
                'active_challenges_count' => PartnerChallenge::where('partner_id', $partner->id)->where('status', 'active')->count(),
                'total_challenges_count' => PartnerChallenge::where('partner_id', $partner->id)->count(),
                'total_programs_count' => $metrics['active_programs_count'],
            ]),
        ], 200);
    }

    /** GET /api/v1/partner/reports/chart-data — evolucao mensal e distribuicao geografica reais. */
    public function chartData(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        $series = $this->impact->timeSeries($partner);

        return response()->json([
            'status' => 'success',
            'data' => [
                'interval' => 'monthly',
                'series' => $series,
                'time_series' => $series,
                'geo' => $this->impact->geoDistribution($partner),
            ],
        ], 200);
    }

    /** GET /api/v1/partner/reports/export — CSV agregado por desafio (sem dados pessoais). */
    public function export(Request $request): StreamedResponse
    {
        $partner = $this->partner($request);
        $challenges = PartnerChallenge::with('program')->where('partner_id', $partner->id)->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio_impacto_kivara_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($challenges) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nome do Programa', 'Nome do Desafio', 'Províncias', 'Alunos Alcançados', 'KVC Reservado', 'Taxa de Conclusão (%)', 'Data de Conclusão'], ';');

            foreach ($challenges as $ch) {
                $rows = DB::table('classroom_challenge')
                    ->join('classrooms', 'classrooms.id', '=', 'classroom_challenge.classroom_id')
                    ->leftJoin('tenants', 'tenants.id', '=', 'classrooms.tenant_id')
                    ->where('classroom_challenge.partner_challenge_id', $ch->id)
                    ->get(['classroom_challenge.classroom_id', 'classroom_challenge.current_progress', 'tenants.province']);

                $students = $this->impact->studentsOf($rows->pluck('classroom_id')->unique());
                $target = max((int) $ch->goal_target, 1);
                $rate = $rows->isEmpty() ? 0 : round($rows->avg(fn ($r) => min((int) $r->current_progress / $target, 1)) * 100, 1);

                fputcsv($out, [
                    $ch->program?->title ?? 'Campanha Geral KIVARA',
                    $ch->title,
                    $rows->pluck('province')->filter()->unique()->implode(', ') ?: '—',
                    $students['child']->count() + $students['user']->count(),
                    number_format((float) $ch->reward_pool, 0, ',', '.') . ' KVC',
                    $rate . '%',
                    $ch->ends_at ? $ch->ends_at->format('d/m/Y') : 'Em curso',
                ], ';');
            }

            fclose($out);
        }, 200, $headers);
    }

    private function partner(Request $request): Partner
    {
        return \App\Support\PartnerContext::resolve($request);
    }
}
