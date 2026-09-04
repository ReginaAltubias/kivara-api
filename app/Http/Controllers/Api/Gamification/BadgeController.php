<?php

namespace App\Http\Controllers\Api\Gamification;

use OpenApi\Attributes as OA;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class BadgeController extends Controller
{
        #[OA\Get(
        path: "/api/v1/gamification/badges",
        summary: "Gamification Badges (GET)",
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
        $badges = Badge::where('is_active', true)->get();
        return response()->json(['status' => 'success', 'data' => $badges], 200);
    }

        #[OA\Get(
        path: "/api/v1/gamification/badges/{id}",
        summary: "Gamification Badges {id} (GET)",
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
        $badge = Badge::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $badge], 200);
    }
}
