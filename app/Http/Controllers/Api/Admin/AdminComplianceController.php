<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RiskFlag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminComplianceController extends Controller
{
    /**
     * KPIs de conformidade para o painel Admin.
     */
        #[OA\Get(
        path: "/api/v1/admin/compliance/stats",
        summary: "Consultar Métricas de Compliance e Risco Regulatório",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_active_tenants' => Tenant::query()->where('is_active', true)->count(),
                'pending_kyc' => User::query()
                    ->where('role', 'parent')
                    ->whereNull('email_verified_at')
                    ->count(),
                'unresolved_high_risks' => RiskFlag::query()
                    ->where('risk_level', 'high')
                    ->where('is_reviewed', false)
                    ->count(),
                'total_audit_events_today' => AuditLog::query()
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ]);
    }
}
