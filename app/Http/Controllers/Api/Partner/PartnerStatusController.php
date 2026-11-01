<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerProgram;
use App\Support\PartnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerStatusController extends Controller
{
    /** GET /api/v1/partner/status — estado da candidatura do parceiro autenticado. */
    public function show(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);

        return response()->json([
            'status' => 'success',
            'data' => [
                'partner_id' => $partner->id,
                'name' => $partner->name,
                'approval_status' => $partner->status,
                'is_active' => (bool) $partner->is_active,
                'rejection_reason' => $partner->rejection_reason,
                'reviewed_at' => $partner->reviewed_at?->toISOString(),
            ],
        ]);
    }

    /** GET /api/v1/partner/limits — quotas do parceiro (configuraveis por ambiente) e consumo atual. */
    public function limits(Request $request): JsonResponse
    {
        $partner = PartnerContext::resolve($request);
        $programs = PartnerProgram::where('partner_id', $partner->id);

        // Limites do plano subscrito (se ativo); sem plano valem os da configuracao
        $limits = $partner->limits();
        $plan = $limits['plan'];

        return response()->json([
            'status' => 'success',
            'data' => [
                'tier_name' => $plan?->name ?? 'Parceiro',
                'tier_id' => $plan?->id,
                'max_programs' => $limits['max_programs'],
                'max_children' => $limits['max_children'],
                'used_programs' => (clone $programs)->count(),
                'used_children' => (int) (clone $programs)->sum('children_target'),
            ],
        ]);
    }
}
