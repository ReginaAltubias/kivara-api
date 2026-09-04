<?php

namespace App\Http\Controllers\Api\School;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\SchoolReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolRewardController extends Controller
{
    /**
     * GET /api/v1/school/rewards
     * 
     * Lista todas as recompensas do catálogo da escola autenticada.
     */
        #[OA\Get(
        path: "/api/v1/school/rewards",
        summary: "School Rewards (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $query = SchoolReward::where('tenant_id', $tenantId);

        // Filtro por estado ativo
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtro: Apenas itens da loja (compráveis pelo aluno)
        if ($request->boolean('store_only')) {
            $query->where('price_in_coins', '>', 0);
        }

        // Filtro: Apenas recompensas simbólicas (para desafios)
        if ($request->boolean('symbolic_only')) {
            $query->where(function ($q) {
                $q->whereNull('price_in_coins')->orWhere('price_in_coins', 0);
            });
        }

        $rewards = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $rewards,
        ], 200);
    }

    /**
     * POST /api/v1/school/rewards
     * 
     * Cria uma nova recompensa no catálogo da escola.
     */
        #[OA\Post(
        path: "/api/v1/school/rewards",
        summary: "School Rewards (POST)",
        tags: ["B2B - Escolas & Professores"],
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
        $tenantId = $this->getTenantId($request);

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string|max:1000',
            'icon'           => 'nullable|string|max:255',
            'price_in_coins' => 'nullable|integer|min:0',
            'stock'          => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $reward = SchoolReward::create([
            'tenant_id'      => $tenantId,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'icon'           => $validated['icon'] ?? '🎁',
            'price_in_coins' => $validated['price_in_coins'] ?? null,
            'stock'          => $validated['stock'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Recompensa institucional criada com sucesso.',
            'data'    => $reward,
        ], 201);
    }

    /**
     * GET /api/v1/school/rewards/{id}
     * 
     * Detalhes de uma recompensa específica.
     */
        #[OA\Get(
        path: "/api/v1/school/rewards/{id}",
        summary: "School Rewards {id} (GET)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $reward = SchoolReward::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $reward,
        ], 200);
    }

    /**
     * PATCH / PUT /api/v1/school/rewards/{id}
     * 
     * Atualiza uma recompensa existente.
     */
        #[OA\Patch(
        path: "/api/v1/school/rewards/{id}",
        summary: "School Rewards {id} (PATCH)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $reward = SchoolReward::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string|max:1000',
            'icon'           => 'nullable|string|max:255',
            'price_in_coins' => 'nullable|integer|min:0',
            'stock'          => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $reward->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Recompensa atualizada com sucesso.',
            'data'    => $reward,
        ], 200);
    }

    /**
     * DELETE /api/v1/school/rewards/{id}
     * 
     * Remove uma recompensa do catálogo da escola.
     */
        #[OA\Delete(
        path: "/api/v1/school/rewards/{id}",
        summary: "School Rewards {id} (DELETE)",
        tags: ["B2B - Escolas & Professores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 204, description: "Recurso removido com sucesso.")
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);

        $reward = SchoolReward::where('tenant_id', $tenantId)->findOrFail($id);

        $reward->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Recompensa removida com sucesso.',
        ], 200);
    }

    /**
     * Helper para obter e validar o tenant_id do utilizador autenticado
     */
    private function getTenantId(Request $request): string
    {
        $tenantId = $request->user()->tenant_id;

        if (!$tenantId) {
            abort(403, 'O utilizador autenticado não está associado a nenhuma instituição de ensino.');
        }

        return $tenantId;
    }
}
