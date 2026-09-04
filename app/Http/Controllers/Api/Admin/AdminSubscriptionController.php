<?php

namespace App\Http\Controllers\Api\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSubscriptionController extends Controller
{
    /**
     * Lista planos ordenados por preço mensal.
     */
        #[OA\Get(
        path: "/api/v1/admin/subscription-plans",
        summary: "Listar Planos de Subscrição",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->orderBy('monthly_price')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $plans,
        ]);
    }

    /**
     * Cria um novo plano de subscrição.
     */
        #[OA\Post(
        path: "/api/v1/admin/subscription-plans",
        summary: "Criar Plano de Subscrição",
        tags: ["Administração Central"],
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
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:free,family,school,partner',
                'monthly_price' => 'nullable|numeric|min:0',
                'annual_price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'max_children' => 'nullable|integer|min:0',
                'max_classrooms' => 'nullable|integer|min:0',
                'features' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            $plan = SubscriptionPlan::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Plano de subscrição criado com sucesso.',
                'data' => $plan,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível criar o plano de subscrição.',
            ], 500);
        }
    }

    /**
     * Detalhe de um plano.
     */
        #[OA\Get(
        path: "/api/v1/admin/subscription-plans/{subscription_plan}",
        summary: "Obter Plano de Subscrição",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(string $subscription_plan): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($subscription_plan);

        return response()->json([
            'status' => 'success',
            'data' => $plan,
        ]);
    }

    /**
     * Atualiza um plano existente.
     */
        #[OA\Patch(
        path: "/api/v1/admin/subscription-plans/{subscription_plan}",
        summary: "Atualizar Plano de Subscrição",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $subscription_plan): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($subscription_plan);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|in:free,family,school,partner',
                'monthly_price' => 'nullable|numeric|min:0',
                'annual_price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'max_children' => 'nullable|integer|min:0',
                'max_classrooms' => 'nullable|integer|min:0',
                'features' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            $plan->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Plano de subscrição atualizado com sucesso.',
                'data' => $plan->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível atualizar o plano de subscrição.',
            ], 500);
        }
    }

    /**
     * Remove um plano.
     */
        #[OA\Delete(
        path: "/api/v1/admin/subscription-plans/{subscription_plan}",
        summary: "Admin Subscription Plans {subscription Plan} (DELETE)",
        tags: ["Administração Central"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(string $subscription_plan): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($subscription_plan);
        $plan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Plano de subscrição removido com sucesso.',
        ]);
    }
}
