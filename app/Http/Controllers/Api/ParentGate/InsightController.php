<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\DreamVault;
use App\Models\Insight;
use App\Models\LedgerEntry;
use App\Models\Task;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InsightController extends Controller
{
    /**
     * Gera e retorna insights comportamentais e de literacia financeira (AI Powered).
     */
        #[OA\Post(
        path: "/api/v1/insights/generate",
        summary: "Insights Generate (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'child_id' => 'nullable|uuid|exists:child_profiles,id',
            'childId' => 'nullable|uuid|exists:child_profiles,id',
            'period' => 'nullable|in:weekly,monthly',
        ]);

        $childId = $validated['child_id'] ?? $validated['childId'] ?? null;
        $period = $validated['period'] ?? 'monthly';
        $startDate = $period === 'weekly' ? Carbon::now()->subDays(7) : Carbon::now()->subDays(30);

        $childrenQuery = ChildProfile::where('parent_id', $user->id);
        if ($childId) {
            $childrenQuery->where('id', $childId);
        }
        $children = $childrenQuery->get();

        if ($children->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'insights' => [],
                'data' => [],
            ], 200);
        }

        $generatedInsights = [];

        foreach ($children as $child) {
            // 1. Análise de Tarefas
            $totalTasks = Task::where('child_id', $child->id)
                ->where('created_at', '>=', $startDate)
                ->count();

            $completedTasks = Task::where('child_id', $child->id)
                ->where('created_at', '>=', $startDate)
                ->where('status', 'completed')
                ->count();

            if ($totalTasks > 0) {
                $completionRate = round(($completedTasks / $totalTasks) * 100);
                if ($completionRate >= 80) {
                    $generatedInsights[] = [
                        'id' => (string) Str::uuid(),
                        'child_id' => $child->id,
                        'child_name' => $child->name,
                        'title' => "Excelente Conclusão de Tarefas ({$completionRate}%)",
                        'description' => "{$child->name} concluiu {$completedTasks} de {$totalTasks} tarefas atribuídas no período.",
                        'type' => 'positive',
                        'category' => 'tasks',
                        'actionable_advice' => 'Elogie a consistência e considere propor uma nova meta de poupança no cofre.',
                    ];
                } elseif ($completionRate < 50) {
                    $generatedInsights[] = [
                        'id' => (string) Str::uuid(),
                        'child_id' => $child->id,
                        'child_name' => $child->name,
                        'title' => "Oportunidade de Engajamento em Tarefas",
                        'description' => "{$child->name} tem tarefas pendentes de conclusão neste período.",
                        'type' => 'suggestion',
                        'category' => 'tasks',
                        'actionable_advice' => 'Converse sobre a importância da cooperação familiar e reveja se as recompensas são atrativas.',
                    ];
                }
            }

            // 2. Análise de Poupança nos Cofres de Sonhos
            $vaults = DreamVault::where('child_profile_id', $child->id)->get();
            $totalSavedInVaults = $vaults->sum('current_amount');
            $totalTargetInVaults = $vaults->sum('target_amount');

            if ($vaults->isNotEmpty()) {
                $overallVaultProgress = $totalTargetInVaults > 0 ? round(($totalSavedInVaults / $totalTargetInVaults) * 100) : 0;

                if ($overallVaultProgress >= 70) {
                    $generatedInsights[] = [
                        'id' => (string) Str::uuid(),
                        'child_id' => $child->id,
                        'child_name' => $child->name,
                        'title' => "Meta de Sonho Quase Atingida!",
                        'description' => "O progresso global de poupança nos cofres atingiu {$overallVaultProgress}%.",
                        'type' => 'positive',
                        'category' => 'saving',
                        'actionable_advice' => 'Prepare-se para celebrar a conquista quando a meta for concluída!',
                    ];
                } else {
                    $generatedInsights[] = [
                        'id' => (string) Str::uuid(),
                        'child_id' => $child->id,
                        'child_name' => $child->name,
                        'title' => "Incentivo à Poupança Contínua",
                        'description' => "{$child->name} tem {$totalSavedInVaults} KVC acumulados nos seus cofres de sonhos.",
                        'type' => 'suggestion',
                        'category' => 'saving',
                        'actionable_advice' => 'Ofereça uma taxa de incentivo parental (match de 10% ou 20%) ao próximo depósito no cofre.',
                    ];
                }
            }

            // 3. Análise de Gastos e Orçamento
            $wallet = Wallet::where('walletable_id', $child->id)->where('walletable_type', ChildProfile::class)->first();
            if ($wallet) {
                $spendingInPeriod = LedgerEntry::where('wallet_id', $wallet->id)
                    ->where('direction', 'debit')
                    ->where('created_at', '>=', $startDate)
                    ->sum('amount');

                if ((float) $child->monthly_budget > 0 && $spendingInPeriod > (float) $child->monthly_budget) {
                    $generatedInsights[] = [
                        'id' => (string) Str::uuid(),
                        'child_id' => $child->id,
                        'child_name' => $child->name,
                        'title' => "Alerta de Orçamento Ultrapassado",
                        'description' => "Os gastos totais ultrapassaram o orçamento mensal definido ({$spendingInPeriod} / {$child->monthly_budget} KVC).",
                        'type' => 'alert',
                        'category' => 'spending',
                        'actionable_advice' => 'Reveja com a criança as prioridades de compra para o próximo mês.',
                    ];
                }
            }

            // 4. Insight de Gamificação Pedagógica
            if ((int) $child->total_xp > 100) {
                $generatedInsights[] = [
                    'id' => (string) Str::uuid(),
                    'child_id' => $child->id,
                    'child_name' => $child->name,
                    'title' => "Evolução do Nível de Literacia",
                    'description' => "{$child->name} já acumulou {$child->total_xp} XP em missões e tarefas práticas.",
                    'type' => 'positive',
                    'category' => 'learning',
                    'actionable_advice' => 'Incentive a realização do próximo módulo de lições financeiras.',
                ];
            }
        }

        // Se nenhum insight específico foi gerado, retorna conselhos universais
        if (empty($generatedInsights)) {
            $generatedInsights[] = [
                'id' => (string) Str::uuid(),
                'title' => 'Primeiros Passos na Literacia Financeira',
                'description' => 'Defina a primeira meta no Cofre de Sonhos e atribua tarefas para incentivar a conquista de moedas.',
                'type' => 'suggestion',
                'category' => 'learning',
                'actionable_advice' => 'Crie um cofre de poupança conjunto com a criança hoje.',
            ];
        }

        // Opcional: Persistir insights no banco de dados
        foreach ($generatedInsights as $ins) {
            Insight::create([
                'child_id' => $ins['child_id'] ?? $children->first()->id,
                'title' => $ins['title'],
                'description' => $ins['description'],
                'type' => $ins['type'],
                'category' => $ins['category'],
                'actionable_advice' => $ins['actionable_advice'],
                'period' => $period,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'insights' => $generatedInsights,
            'data' => $generatedInsights,
        ], 200);
    }
}
