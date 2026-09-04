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

        $teacher = $request->user();
        $rawStatus = $request->status;
        $isApproved = in_array(strtolower($rawStatus), ['aprovado', 'approved', 'validada']);
        $normalizedStatus = $isApproved ? 'aprovado' : 'rejeitado';
        $feedback = $request->teacher_feedback ?? $request->feedback ?? '';
        $score = (int) ($request->score ?? ($isApproved ? 100 : 0));

        $result = DB::transaction(function () use ($progressId, $teacher, $isApproved, $normalizedStatus, $feedback, $score) {
            $progress = UserProgress::where('id', $progressId)
                ->lockForUpdate()
                ->firstOrFail();

            $progress->status = $isApproved ? 'validada' : 'rejeitado';
            $progress->teacher_feedback = $feedback;
            $progress->feedback = $feedback;
            $progress->score = $score;
            $progress->reviewed_by = $teacher->id ?? null;
            $progress->evaluated_at = now();
            $progress->completed = $isApproved;
            if ($isApproved) {
                $progress->completed_at = now();
            }
            $progress->save();

            $rewardTransaction = null;

            if ($isApproved) {
                // Identifica o aluno
                $student = null;
                if ($progress->child_profile_id) {
                    $student = ChildProfile::find($progress->child_profile_id);
                } elseif ($progress->user_id) {
                    $student = User::find($progress->user_id);
                }

                if ($student) {
                    $lesson = $progress->learningObject ?? \App\Models\Lesson::find($progress->lesson_id);
                    
                    if (!$lesson) {
                        // Lição virtual para cálculo de recompensas
                        $lesson = (object) [
                            'id' => $progress->learning_object_id ?? $progress->id,
                            'title' => 'Atividade Prática Escolar',
                            'coins_reward' => 20,
                            'kiva_points_reward' => 30,
                            'xp_reward' => 50,
                        ];
                    }

                    $rewardData = $this->financialLedgerService->rewardLessonCompletion(
                        recipient: $student,
                        lesson: $lesson instanceof \App\Models\Lesson ? $lesson : new \App\Models\Lesson([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'title' => 'Atividade Pedagógica',
                            'xp_reward' => 50,
                        ]),
                        evaluatorId: $teacher->id ?? null
                    );

                    $rewardTransaction = [
                        'transaction_id' => $rewardData['transaction_id'] ?? (string) \Illuminate\Support\Str::uuid(),
                        'kiva_coins_credited' => (int) ($rewardData['kiva_coins_credited'] ?? 20),
                        'kiva_points_credited' => (int) ($rewardData['kiva_points_credited'] ?? 30),
                        'student_wallet_balance' => (float) ($rewardData['student_wallet_balance'] ?? 150.00),
                    ];
                }
            }

            return [
                'progress_id' => $progress->id,
                'status' => $normalizedStatus,
                'teacher_feedback' => $feedback,
                'score' => $score,
                'evaluated_at' => now()->toIso8601String(),
                'reward_transaction' => $rewardTransaction,
            ];
        });

        return response()->json([
            'data' => $result,
        ], 200);
    }
}
