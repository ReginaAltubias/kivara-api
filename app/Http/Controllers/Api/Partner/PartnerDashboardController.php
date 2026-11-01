<?php

namespace App\Http\Controllers\Api\Partner;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PartnerChallenge;
use Illuminate\Support\Facades\DB;

class PartnerDashboardController extends Controller
{
        #[OA\Get(
        path: "/api/v1/partner/dashboard",
        summary: "Partner Dashboard (GET)",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request)
    {
        $partnerId = \App\Support\PartnerContext::resolve($request)->id;

        // Fetch challenges for this partner
        $challenges = PartnerChallenge::where('partner_id', $partnerId)->get();
        $challengeIds = $challenges->pluck('id');

        // Total Budget Spent (for concluded challenges)
        $totalBudgetSpent = PartnerChallenge::where('partner_id', $partnerId)
            ->where('status', 'completed')
            ->sum('reward_pool');

        // Total Participants
        // We join classroom_challenge with classroom_user
        $totalParticipants = DB::table('classroom_challenge')
            ->whereIn('partner_challenge_id', $challengeIds)
            ->join('classroom_user', 'classroom_challenge.classroom_id', '=', 'classroom_user.classroom_id')
            ->where('classroom_user.role', 'student')
            ->count(DB::raw('DISTINCT COALESCE(classroom_user.child_profile_id, classroom_user.user_id)'));

        // Classes Involved
        $classesInvolved = DB::table('classroom_challenge')
            ->whereIn('partner_challenge_id', $challengeIds)
            ->count(DB::raw('DISTINCT classroom_id'));

        return response()->json([
            'metrics' => [
                'total_budget_spent' => $totalBudgetSpent,
                'total_participants' => $totalParticipants,
                'classes_involved' => $classesInvolved,
            ],
            'active_campaigns' => $challenges->where('status', 'active')->values(),
            'completed_campaigns' => $challenges->where('status', 'completed')->values(),
        ]);
    }
}
