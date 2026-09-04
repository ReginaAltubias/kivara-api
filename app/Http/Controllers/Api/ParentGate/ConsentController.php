<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Consent;
use Illuminate\Http\JsonResponse;

class ConsentController extends Controller
{
        #[OA\Get(
        path: "/api/v1/consents/{childProfileId}",
        summary: "Consents {childProfileId} (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request, string $childProfileId): JsonResponse
    {
        $consents = Consent::where('child_profile_id', $childProfileId)->get();
        return response()->json(['data' => $consents], 200);
    }

        #[OA\Post(
        path: "/api/v1/consents/{childProfileId}/update",
        summary: "Consents {childProfileId} Update (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function update(Request $request, string $childProfileId): JsonResponse
    {
        $request->validate([
            'consent_type' => 'required|string',
            'is_granted' => 'required|boolean',
        ]);

        $consent = Consent::updateOrCreate(
            ['child_profile_id' => $childProfileId, 'consent_type' => $request->consent_type],
            ['is_granted' => $request->is_granted]
        );

        return response()->json(['data' => $consent], 200);
    }
}
