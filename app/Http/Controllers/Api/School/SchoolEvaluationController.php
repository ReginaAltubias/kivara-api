<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\User;
use App\Models\UserProgress;
use App\Services\FinancialLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolEvaluationController extends Controller
{
    public function __construct(
        protected FinancialLedgerService $financialLedgerService
    ) {}

    /**
     * POST /api/v1/school/evaluations/{progress_id}
     * Avaliação do Professor & Crédito Imediato em Wallet
     */
        #[OA\Post(
        path: "/api/v1/teacher/evaluations/{progressId}",
        summary: "Teacher Evaluations {progressId} (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function evaluate(Request $request, string $progressId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:aprovado,rejeitado,approved,rejected',
            'teacher_feedback' => 'nullable|string|max:1000',
            'feedback' => 'nullable|string|max:1000',
            'score' => 'nullable|integer|min:0|max:100',
        ]);

        // Mesma avaliacao que o fluxo /b2b/evaluations (estado, XP/moedas e feedback), sem logica duplicada.
        $isApproved = in_array(strtolower($request->status), ['aprovado', 'approved', 'validada'], true);
        $evaluator = app(MissionEvaluationController::class);

        return $isApproved
            ? $evaluator->approve($request, $progressId)
            : $evaluator->reject($request, $progressId);
    }
}
