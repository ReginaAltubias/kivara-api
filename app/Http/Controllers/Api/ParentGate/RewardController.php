<?php

namespace App\Http\Controllers\Api\ParentGate;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\LedgerEntry;
use App\Models\Reward;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardController extends Controller
{
    /**
     * Lista o catálogo de recompensas familiares.
     */
        #[OA\Get(
        path: "/api/v1/rewards",
        summary: "Rewards (GET)",
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
        $user = $request->user();

        $query = Reward::query()
            ->whereHas('child', function ($q) use ($user) {
                $q->where('parent_id', $user->id)->orWhere('id', $user->id);
            })
            ->with(['child:id,name,avatar,parent_id'])
            ->orderByDesc('created_at');

        if ($request->filled('child_id')) {
            $query->where('child_id', $request->string('child_id'));
        }

        $rewards = $query->get()->map(fn (Reward $r) => $this->transformReward($r));

        return response()->json([
            'status' => 'success',
            'data' => $rewards,
        ], 200);
    }

    /**
     * Cria uma nova recompensa personalizada.
     */
        #[OA\Post(
        path: "/api/v1/rewards",
        summary: "Rewards (POST)",
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
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cost' => 'required|numeric|min:1',
            'child_id' => [
                'required',
                'uuid',
                'exists:child_profiles,id',
                function ($attribute, $value, $fail) use ($user) {
                    $exists = ChildProfile::where('id', $value)
                        ->where(function ($q) use ($user) {
                            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
                        })->exists();
                    if (!$exists) {
                        $fail('Criança não autorizada para criação de recompensa.');
                    }
                },
            ],
            'icon' => 'nullable|string|max:100',
        ]);

        $reward = Reward::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cost' => $validated['cost'],
            'child_id' => $validated['child_id'],
            'icon' => $validated['icon'] ?? '🎁',
        ]);

        $reward->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Recompensa criada com sucesso.',
            'data' => $this->transformReward($reward),
        ], 201);
    }

    /**
     * Detalhes de uma recompensa.
     */
        #[OA\Get(
        path: "/api/v1/rewards/{id}",
        summary: "Rewards {id} (GET)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $reward = Reward::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })
        ->with('child:id,name,avatar')
        ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformReward($reward),
        ], 200);
    }

    /**
     * Atualiza uma recompensa.
     */
        #[OA\Patch(
        path: "/api/v1/rewards/{id}",
        summary: "Rewards {id} (PATCH)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $reward = Reward::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cost' => 'nullable|numeric|min:1',
            'icon' => 'nullable|string|max:100',
        ]);

        $reward->update($validated);
        $reward->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Recompensa atualizada com sucesso.',
            'data' => $this->transformReward($reward),
        ], 200);
    }

    /**
     * Elimina uma recompensa.
     */
        #[OA\Delete(
        path: "/api/v1/rewards/{id}",
        summary: "Rewards {id} (DELETE)",
        tags: ["Geral"],
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
        $user = $request->user();

        $reward = Reward::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $reward->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Recompensa eliminada.',
        ], 200);
    }

    /**
     * Resgata uma recompensa pela criança (com débito de KVC na carteira).
     */
        #[OA\Post(
        path: "/api/v1/rewards/{id}/claim",
        summary: "Rewards {id} Claim (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function claim(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $reward = Reward::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $result = DB::transaction(function () use ($reward, $user) {
            $child = ChildProfile::lockForUpdate()->findOrFail($reward->child_id);
            $wallet = Wallet::where('walletable_id', $child->id)
                ->where('walletable_type', ChildProfile::class)
                ->lockForUpdate()
                ->first();

            $cost = (float) $reward->cost;

            if (!$wallet || (float) $wallet->balance < $cost) {
                abort(422, 'Saldo insuficiente em KVC para resgatar esta recompensa.');
            }

            $idempotencyKey = 'REW-CLM-' . $reward->id . '-' . Str::random(8);

            LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'debit',
                'entry_type' => 'purchase',
                'amount' => $cost,
                'description' => 'Resgate da recompensa: ' . $reward->title,
                'metadata' => [
                    'reward_id' => $reward->id,
                    'claimed_by' => $user->id,
                ],
            ]);

            $wallet->balance = (float) $wallet->balance - $cost;
            $wallet->save();

            return [
                'reward' => $reward,
                'cost_paid' => $cost,
                'new_wallet_balance' => (float) $wallet->balance,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Recompensa resgatada com sucesso! ' . $result['cost_paid'] . ' KVC debitados.',
            'data' => $this->transformReward($result['reward']),
            'wallet_balance' => $result['new_wallet_balance'],
        ], 200);
    }

    /**
     * Sugestões de recompensas pedagógicas e familiares.
     */
        #[OA\Post(
        path: "/api/v1/rewards/suggest",
        summary: "Rewards Suggest (POST)",
        tags: ["Geral"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function suggest(Request $request): JsonResponse
    {
        $suggestions = [
            [
                'title' => 'Ir ao Cinema em Família',
                'description' => 'Bilhete com direito a pipocas para ver o filme favorito.',
                'suggested_cost' => 500,
                'cost' => 500,
                'icon' => '🍿',
                'category' => 'experience',
            ],
            [
                'title' => 'Escolher o Jantar de Sexta-feira',
                'description' => 'A criança decide a refeição favorita de toda a família (ex: Pizza, Hambúrguer).',
                'suggested_cost' => 300,
                'cost' => 300,
                'icon' => '🍕',
                'category' => 'privilege',
            ],
            [
                'title' => '1 Hora Extra de Videojogo / Ecrã',
                'description' => 'Tempo adicional no fim de semana para jogar com amigos.',
                'suggested_cost' => 250,
                'cost' => 250,
                'icon' => '🎮',
                'category' => 'privilege',
            ],
            [
                'title' => 'Passeio ao Parque de Diversões',
                'description' => 'Uma tarde de brincadeiras ao ar livre com a família.',
                'suggested_cost' => 600,
                'cost' => 600,
                'icon' => '🎡',
                'category' => 'experience',
            ],
            [
                'title' => 'Livro ou Banda Desenhada Nova',
                'description' => 'Comprar um livro novo à escolha na livraria.',
                'suggested_cost' => 400,
                'cost' => 400,
                'icon' => '📚',
                'category' => 'education',
            ],
        ];

        return response()->json([
            'status' => 'success',
            'suggestions' => $suggestions,
        ], 200);
    }

    /**
     * Formatação do modelo Reward.
     */
    private function transformReward(Reward $reward): array
    {
        return [
            'id' => $reward->id,
            'reward_id' => $reward->id,
            'child_id' => $reward->child_id,
            'title' => $reward->title,
            'description' => $reward->description,
            'cost' => (float) $reward->cost,
            'icon' => $reward->icon ?: '🎁',
            'child' => $reward->child ? [
                'id' => $reward->child->id,
                'name' => $reward->child->name,
                'avatar' => $reward->child->avatar,
            ] : null,
            'created_at' => $reward->created_at?->toISOString(),
            'updated_at' => $reward->updated_at?->toISOString(),
        ];
    }
}
