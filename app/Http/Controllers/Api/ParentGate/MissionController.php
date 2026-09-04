<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Mission;
use Illuminate\Http\JsonResponse;

class MissionController extends Controller
{
        #[OA\Get(
        path: "/api/v1/missions",
        summary: "Missions (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $missions = Mission::whereHas('child', function($q) use ($request) {
            $q->where('parent_id', $request->user()->id);
        })->get();
        return response()->json(['data' => $missions], 200);
    }

        #[OA\Post(
        path: "/api/v1/missions",
        summary: "Missions (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string',
            'reward_amount' => 'required|numeric',
            'child_id' => 'required|exists:child_profiles,id',
        ]);

        $mission = Mission::create($request->all());
        return response()->json(['data' => $mission], 201);
    }
}
