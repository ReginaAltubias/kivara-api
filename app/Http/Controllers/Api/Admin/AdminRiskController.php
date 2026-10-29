<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRiskFlagRequest;
use App\Models\RiskFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRiskController extends Controller
{
    /**
     * Lista risk flags com filtros de estado e nível.
     */
        #[OA\Get(
        path: "/api/v1/admin/risk-flags",
        summary: "Listar Alertas de Risco",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $flags = RiskFlag::query()
            ->with([
                'wallet.tenant:id,name,tenant_type,is_active',
                'wallet.walletable',
                'ledgerEntry:id,wallet_id,amount,direction,entry_type,created_at',
                'reviewer:id,name,email',
            ])
            ->when($request->boolean('unresolved'), fn ($query) => $query->where('is_reviewed', false))
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->string('status')->toString();

                if ($status === 'reviewed') {
                    $query->where('is_reviewed', true);
                }

                if ($status === 'pending') {
                    $query->where('is_reviewed', false);
                }
            })
            ->when($request->filled('risk_level'), function ($query) use ($request) {
                $query->where('risk_level', $request->string('risk_level'));
            })
            ->latest()
            ->paginate(min((int) $request->query('limit', 50), 200));

        return response()->json([
            'status' => 'success',
            'data' => $flags->items(),
            'pagination' => [
                'current_page' => $flags->currentPage(),
                'per_page' => $flags->perPage(),
                'total' => $flags->total(),
                'last_page' => $flags->lastPage(),
            ],
        ]);
    }

    /**
     * Admin trata / marca uma flag de risco.
     */
        #[OA\Patch(
        path: "/api/v1/admin/risk-flags/{riskFlag}",
        summary: "Listar Alertas de Risco",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(UpdateRiskFlagRequest $request, RiskFlag $riskFlag): JsonResponse
    {
        $validated = $request->validated();

        $riskFlag->fill([
            'admin_notes' => $validated['admin_notes'] ?? $riskFlag->admin_notes,
            'is_reviewed' => $validated['is_reviewed'],
            'reviewed_by' => $request->user()->id,
        ]);
        $riskFlag->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Flag de risco atualizada com sucesso.',
            'data' => $riskFlag->fresh()->load([
                'wallet.tenant:id,name,tenant_type',
                'wallet.walletable',
                'reviewer:id,name,email',
            ]),
        ]);
    }
}
