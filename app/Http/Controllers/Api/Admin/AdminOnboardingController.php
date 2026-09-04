<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOnboardingController extends Controller
{
    /**
     * Métricas do funil de onboarding (calculadas on-the-fly).
     */
        #[OA\Get(
        path: "/api/v1/admin/onboarding-analytics",
        summary: "Admin Onboarding Analytics (GET)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $now = now();

        $totalRegistrationsThisMonth = User::query()
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $totalParents = User::query()->where('role', 'parent')->count();

        // Sem tabela tenant_subscriptions: converte = parent com tenant pago (subscription_tier_id)
        $convertedParents = User::query()
            ->where('role', 'parent')
            ->whereNotNull('tenant_id')
            ->whereHas('tenant', fn ($q) => $q->whereNotNull('subscription_tier_id'))
            ->count();

        $conversionRate = $totalParents > 0
            ? round(($convertedParents / $totalParents) * 100, 2)
            : 0.0;

        $dropOffCount = User::query()
            ->where('role', 'parent')
            ->where('created_at', '<=', now()->subDays(7))
            ->where(function ($query) {
                $query->whereNull('family_id')
                    ->orWhereDoesntHave('childProfiles');
            })
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_registrations_this_month' => $totalRegistrationsThisMonth,
                'conversion_rate' => $conversionRate,
                'converted_parents' => $convertedParents,
                'total_parents' => $totalParents,
                'drop_off_count' => $dropOffCount,
                'generated_at' => $now->toIso8601String(),
            ],
        ]);
    }
}
