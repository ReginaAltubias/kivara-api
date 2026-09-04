<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionInvoice;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
        #[OA\Get(
        path: "/api/v1/subscription/tiers",
        summary: "Subscription Tiers (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function tiers(): JsonResponse
    {
        $tiers = SubscriptionPlan::all();
        return response()->json(['data' => $tiers], 200);
    }

        #[OA\Post(
        path: "/api/v1/subscription/subscribe",
        summary: "Subscription Subscribe (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'tier_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|string',
        ]);

        return response()->json(['message' => 'Subscrição iniciada com sucesso'], 200);
    }

        #[OA\Post(
        path: "/api/v1/subscription/cancel",
        summary: "Subscription Cancel (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function cancel(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Subscrição cancelada com sucesso'], 200);
    }

        #[OA\Get(
        path: "/api/v1/subscription/invoices",
        summary: "Subscription Invoices (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function invoices(Request $request): JsonResponse
    {
        $invoices = SubscriptionInvoice::where('family_id', $request->user()->family_id)->get();
        return response()->json(['data' => $invoices], 200);
    }
}
