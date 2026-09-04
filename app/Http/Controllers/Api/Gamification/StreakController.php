<?php

namespace App\Http\Controllers\Api\Gamification;

use OpenApi\Attributes as OA;

use App\Models\Streak;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class StreakController extends Controller
{
        #[OA\Post(
        path: "/api/v1/gamification/streaks/record",
        summary: "Gamification Streaks Record (POST)",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function record(Request $request): JsonResponse
    {
        $request->validate(['activity_type' => 'required|string']);
        
        $streak = Streak::firstOrCreate(['child_profile_id' => $request->user()->id]);
        $today = now()->startOfDay();
        $lastActivity = $streak->last_activity_at?->startOfDay();

        if ($lastActivity?->equalTo($today)) {
            $maintained = true;
        } elseif ($lastActivity?->diffInDays($today) === 1) {
            $streak->current_streak++;
            $maintained = true;
        } else {
            $streak->current_streak = 1;
            $maintained = false;
        }

        $streak->last_activity_at = now();
        if ($streak->current_streak > $streak->best_streak) {
            $streak->best_streak = $streak->current_streak;
        }
        $streak->save();

        return response()->json(['status' => 'success', 'data' => ['current_streak' => $streak->current_streak, 'maintained' => $maintained]], 200);
    }

        #[OA\Get(
        path: "/api/v1/gamification/streaks/me",
        summary: "Gamification Streaks Me (GET)",
        tags: ["Motor Educacional & RPG"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $streak = Streak::where('child_profile_id', $request->user()->id)->firstOrCreate(['child_profile_id' => $request->user()->id]);
        return response()->json(['status' => 'success', 'data' => $streak], 200);
    }
}
