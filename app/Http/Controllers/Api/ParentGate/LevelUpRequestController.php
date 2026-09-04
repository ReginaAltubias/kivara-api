<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Models\LevelUpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class LevelUpRequestController extends Controller
{
        #[OA\Get(
        path: "/api/v1/parents/level-up-requests",
        summary: "Parents Level Up Requests (GET)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $requests = LevelUpRequest::where('parent_id', $request->user()->id)->get();
        return response()->json(['status' => 'success', 'data' => $requests], 200);
    }

        #[OA\Get(
        path: "/api/v1/parents/level-up-requests/{id}",
        summary: "Parents Level Up Requests {id} (GET)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $request_data = LevelUpRequest::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $request_data], 200);
    }

        #[OA\Post(
        path: "/api/v1/parents/level-up-requests/{id}/approve",
        summary: "Parents Level Up Requests {id} Approve (POST)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function approve(Request $request, string $id): JsonResponse
    {
        $levelUp = LevelUpRequest::findOrFail($id);
        if ($levelUp->parent_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Não autorizado'], 403);
        }

        $levelUp->update(['status' => 'approved', 'reviewed_at' => now()]);
        $levelUp->child()->update(['kivara_phase_id' => $levelUp->to_phase]);

        return response()->json(['status' => 'success', 'message' => 'Nível desbloqueado', 'data' => $levelUp], 200);
    }

        #[OA\Post(
        path: "/api/v1/parents/level-up-requests/{id}/reject",
        summary: "Parents Level Up Requests {id} Reject (POST)",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function reject(Request $request, string $id): JsonResponse
    {
        $levelUp = LevelUpRequest::findOrFail($id);
        if ($levelUp->parent_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Não autorizado'], 403);
        }

        $levelUp->update(['status' => 'rejected', 'reviewed_at' => now(), 'rejection_reason' => $request->rejection_reason]);
        return response()->json(['status' => 'success', 'message' => 'Pedido rejeitado', 'data' => $levelUp], 200);
    }
}
