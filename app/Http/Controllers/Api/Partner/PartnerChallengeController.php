<?php

namespace App\Http\Controllers\Api\Partner;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerChallenge;
use App\Models\PartnerProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerChallengeController extends Controller
{
    /**
     * GET /api/v1/partner/challenges
     * 
     * Lista todos os desafios criados pelo parceiro autenticado.
     */
        #[OA\Get(
        path: "/api/v1/partner/challenges",
        summary: "Desafios Patrocinados",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $challenges = PartnerChallenge::with(['program:id,title', 'schoolReward:id,name', 'classrooms:id,name'])
            ->where('partner_id', $partner->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($c) {
                return [
                    'id'                        => $c->id,
                    'program_id'                => $c->program_id,
                    'program_title'             => $c->program?->title,
                    'title'                     => $c->title,
                    'description'               => $c->description,
                    'goal_type'                 => $c->goal_type,
                    'goal_target'               => (int) $c->goal_target,
                    'reward_pool'               => (int) $c->reward_pool,
                    'starts_at'                 => $c->starts_at?->toISOString(),
                    'ends_at'                   => $c->ends_at?->toISOString(),
                    'status'                    => $c->status,
                    'school_reward_id'          => $c->school_reward_id,
                    'school_reward_description' => $c->school_reward_description ?? $c->schoolReward?->name,
                    'classrooms_count'          => $c->classrooms->count(),
                    'created_at'                => $c->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $challenges,
        ], 200);
    }

    /**
     * POST /api/v1/partner/challenges
     * 
     * Cria um novo desafio cativando o prémio (reward_pool) do orçamento do Programa.
     * Utiliza DB::transaction() com lockForUpdate para garantir consistência.
     */
        #[OA\Post(
        path: "/api/v1/partner/challenges",
        summary: "Criar Desafio Patrocinado",
        tags: ["B2B - Parceiros & Patrocinadores"],
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
        $partner = $this->getAuthenticatedPartner($request);

        $validated = $request->validate([
            'program_id'                => 'required|uuid|exists:partner_programs,id',
            'title'                     => 'required|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'goal_type'                 => 'required|in:total_xp,lessons_completed,savings_amount,habit,quiz,saving',
            'goal_target'               => 'required|integer|min:1',
            'reward_pool'               => 'required|integer|min:1',
            'starts_at'                 => 'nullable|date',
            'ends_at'                   => 'nullable|date',
            'school_reward_id'          => 'nullable|uuid|exists:school_rewards,id',
            'school_reward_description' => 'nullable|string|max:255',
        ]);

        $goalType = $validated['goal_type'];
        if (!in_array($goalType, ['total_xp', 'lessons_completed', 'savings_amount'])) {
            $goalType = 'total_xp';
        }

        $rewardPool = (int) $validated['reward_pool'];

        $challenge = DB::transaction(function () use ($partner, $validated, $goalType, $rewardPool) {
            // 1. Bloqueio pessimista do programa para evitar race condition de orçamento
            $program = PartnerProgram::where('partner_id', $partner->id)
                ->where('id', $validated['program_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Verificar se o programa tem orçamento suficiente disponível
            $availableBudget = $program->remainingBudget();

            if ($rewardPool > $availableBudget) {
                abort(422, "Orçamento insuficiente no Programa selecionado. Orçamento disponível: " . number_format($availableBudget, 0, ',', '.') . " KVC. Solicitado: " . number_format($rewardPool, 0, ',', '.') . " KVC.");
            }

            // 3. Criar o desafio cativando o prémio do programa
            return PartnerChallenge::create([
                'partner_id'                => $partner->id,
                'partner_name'              => $partner->name,
                'program_id'                => $program->id,
                'title'                     => $validated['title'],
                'description'               => $validated['description'] ?? null,
                'goal_type'                 => $goalType,
                'goal_target'               => $validated['goal_target'],
                'reward_pool'               => $rewardPool,
                'starts_at'                 => $validated['starts_at'] ?? now(),
                'ends_at'                   => $validated['ends_at'] ?? now()->addMonth(),
                'status'                    => 'active',
                'school_reward_id'          => $validated['school_reward_id'] ?? null,
                'school_reward_description' => $validated['school_reward_description'] ?? null,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio criado com sucesso e prémio cativado do orçamento do Programa.',
            'data'    => $challenge->load(['program', 'schoolReward']),
        ], 201);
    }

    /**
     * GET /api/v1/partner/challenges/{id}
     */
        #[OA\Get(
        path: "/api/v1/partner/challenges/{id}",
        summary: "Desafios Patrocinados",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $challenge = PartnerChallenge::with(['program', 'schoolReward', 'classrooms'])
            ->where('partner_id', $partner->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $challenge,
        ], 200);
    }

    /**
     * PATCH /api/v1/partner/challenges/{id}
     */
        #[OA\Patch(
        path: "/api/v1/partner/challenges/{id}",
        summary: "Desafios Patrocinados",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $challenge = PartnerChallenge::where('partner_id', $partner->id)->findOrFail($id);

        $validated = $request->validate([
            'title'                     => 'sometimes|required|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'status'                    => 'nullable|string|in:active,completed,cancelled',
            'starts_at'                 => 'nullable|date',
            'ends_at'                   => 'nullable|date',
            'school_reward_id'          => 'nullable|uuid|exists:school_rewards,id',
            'school_reward_description' => 'nullable|string|max:255',
        ]);

        $challenge->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio atualizado com sucesso.',
            'data'    => $challenge,
        ], 200);
    }

    /**
     * DELETE /api/v1/partner/challenges/{id}
     */
        #[OA\Delete(
        path: "/api/v1/partner/challenges/{id}",
        summary: "Desafios Patrocinados",
        tags: ["B2B - Parceiros & Patrocinadores"],
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
        $partner = $this->getAuthenticatedPartner($request);

        $challenge = PartnerChallenge::where('partner_id', $partner->id)->findOrFail($id);
        $challenge->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Desafio eliminado com sucesso.',
        ], 200);
    }

    /**
     * Helper para identificar o parceiro autenticado
     */
    private function getAuthenticatedPartner(Request $request): Partner
    {
        $user = $request->user();

        if ($user->partner_id) {
            return Partner::findOrFail($user->partner_id);
        }

        if ($user->partner) {
            return $user->partner;
        }

        $firstPartner = Partner::first();
        if ($firstPartner) {
            return $firstPartner;
        }

        return Partner::create([
            'name'          => 'Banco Parceiro KIVARA',
            'contact_email' => $user->email,
            'is_active'     => true,
        ]);
    }
}
