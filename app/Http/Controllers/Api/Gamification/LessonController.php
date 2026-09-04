<?php

namespace App\Http\Controllers\Api\Gamification;

use OpenApi\Attributes as OA;

use App\Models\LearningObject;
use App\Models\UserProgress;
use App\Models\ChildProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class LessonController extends Controller
{
    /**
     * GET /gamification/lessons - Listar Mapa de Trilhas
     */
        #[OA\Get(
        path: "/api/v1/gamification/lessons",
        summary: "Listar Lições Educativas",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // Para crianças autenticadas
        $lessons = LearningObject::where('is_active', true)
            ->select('id', 'title', 'type', 'level', 'reward_xp', 'reward_coins', 'estimated_duration_minutes')
            ->orderBy('level')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'current_phase' => 1, // Obter da criança autenticada em produção
                'trails' => $lessons->groupBy('level'),
            ],
        ], 200);
    }

    /**
     * GET /gamification/lessons/{id} - Carregar Lição Completa
     */
        #[OA\Get(
        path: "/api/v1/gamification/lessons/{id}",
        summary: "Obter Detalhes da Lição Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $lesson = LearningObject::findOrFail($id);

        // Obter progresso do utilizador (se for criança)
        $progress = null;
        if ($request->user() && $request->user()->role === 'child') {
            $progress = UserProgress::where('child_profile_id', $request->user()->id)
                ->where('learning_object_id', $lesson->id)
                ->first();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'reward_xp' => $lesson->reward_xp,
                'reward_coins' => $lesson->reward_coins,
                'minimum_score_percent' => $lesson->minimum_score_percent,
                'content_data' => $lesson->content_data,
                'user_progress' => $progress ? [
                    'completed' => $progress->completed,
                    'best_score' => $progress->best_score,
                    'attempts' => $progress->attempts,
                ] : null,
            ],
        ], 200);
    }

    /**
     * POST /gamification/lessons/{id}/complete - Concluir Lição
     */
        #[OA\Post(
        path: "/api/v1/gamification/lessons/{id}/complete",
        summary: "Criar Nova Lição Educativa",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function complete(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'score_percent' => 'required|integer|min:0|max:100',
            'answers' => 'nullable|array',
            'analytics_data' => 'nullable|array',
        ]);

        $lesson = LearningObject::findOrFail($id);
        
        // Obter criança autenticada
        $childProfileId = $request->user()->id; // Em produção, seria necessário validar

        // Verificar se já foi concluída
        $progress = UserProgress::where('child_profile_id', $childProfileId)
            ->where('learning_object_id', $lesson->id)
            ->first();

        if ($progress && $progress->completed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta lição já foi concluída.',
                'error_code' => 'lesson_already_completed',
            ], 409);
        }

        $passed = $request->score_percent >= $lesson->minimum_score_percent;
        $xpEarned = $passed ? $lesson->reward_xp : 0;
        $coinsEarned = $passed ? $lesson->reward_coins : 0;

        // Criar ou atualizar progresso
        if (!$progress) {
            $progress = UserProgress::create([
                'child_profile_id' => $childProfileId,
                'learning_object_id' => $lesson->id,
                'completed' => $passed,
                'attempts' => 1,
                'best_score' => $request->score_percent,
                'total_xp_earned' => $xpEarned,
                'total_coins_earned' => $coinsEarned,
                'last_attempt_data' => $request->answers,
                'completed_at' => $passed ? now() : null,
            ]);
        } else {
            $progress->update([
                'attempts' => $progress->attempts + 1,
                'best_score' => max($progress->best_score ?? 0, $request->score_percent),
                'completed' => $passed,
                'total_xp_earned' => $progress->total_xp_earned + $xpEarned,
                'total_coins_earned' => $progress->total_coins_earned + $coinsEarned,
                'last_attempt_data' => $request->answers,
                'completed_at' => $passed ? now() : $progress->completed_at,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'passed' => $passed,
                'score_percent' => $request->score_percent,
                'minimum_required' => $lesson->minimum_score_percent,
                'xp_earned' => $xpEarned,
                'coins_earned' => $coinsEarned,
                'badges_unlocked' => [],
                'streak_updated' => true,
                'level_up_requested' => false,
            ],
        ], 200);
    }
}
