<?php

namespace App\Http\Controllers\Api\Finance;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\AllowanceConfig;
use App\Models\ChildProfile;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AllowanceController extends Controller
{
    /**
     * Lista configurações de mesada dos filhos do encarregado autenticado.
     */
        #[OA\Get(
        path: "/api/v1/finance/allowances",
        summary: "Listar Configurações de Mesada",
        tags: ["Motor Financeiro & Saldos"],
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

        $allowances = AllowanceConfig::query()
            ->where('parent_id', $user->id)
            ->with(['child:id,name,avatar,parent_id'])
            ->get()
            ->map(fn (AllowanceConfig $config) => $this->transformAllowance($config));

        return response()->json([
            'status' => 'success',
            'data' => $allowances,
        ], 200);
    }

    /**
     * Cria ou atualiza a configuração de mesada para uma criança.
     */
        #[OA\Post(
        path: "/api/v1/finance/allowances",
        summary: "Criar Nova Regra de Mesada Automática",
        tags: ["Motor Financeiro & Saldos"],
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
            'child_profile_id' => 'nullable|uuid|exists:child_profiles,id',
            'child_id' => 'nullable|uuid|exists:child_profiles,id',
            'base_amount' => 'nullable|numeric|min:1',
            'amount' => 'nullable|numeric|min:1',
            'frequency' => 'required|in:weekly,monthly',
            'next_payment_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $childId = $validated['child_profile_id'] ?? $validated['child_id'];

        if (!$childId) {
            $child = ChildProfile::where('parent_id', $user->id)->first();
            if (!$child) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Criança não encontrada.',
                ], 422);
            }
            $childId = $child->id;
        }

        $baseAmount = $validated['base_amount'] ?? $validated['amount'] ?? 1000;
        $nextDate = $validated['next_payment_date'] ?? ($validated['frequency'] === 'weekly' ? now()->addWeek() : now()->addMonth());

        $allowance = AllowanceConfig::updateOrCreate(
            [
                'child_profile_id' => $childId,
                'parent_id' => $user->id,
            ],
            [
                'base_amount' => $baseAmount,
                'frequency' => $validated['frequency'],
                'next_payment_date' => Carbon::parse($nextDate)->format('Y-m-d'),
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        $allowance->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Configuração de mesada guardada com sucesso.',
            'data' => $this->transformAllowance($allowance),
        ], 201);
    }

    /**
     * Detalhes de uma configuração de mesada.
     */
        #[OA\Get(
        path: "/api/v1/finance/allowances/{id}",
        summary: "Obter Configuração de Mesada",
        tags: ["Motor Financeiro & Saldos"],
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

        $allowance = AllowanceConfig::where('parent_id', $user->id)
            ->with('child:id,name,avatar')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformAllowance($allowance),
        ], 200);
    }

    /**
     * Atualiza a configuração de mesada.
     */
        #[OA\Patch(
        path: "/api/v1/finance/allowances/{id}",
        summary: "Atualizar Regra de Mesada",
        tags: ["Motor Financeiro & Saldos"],
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

        $allowance = AllowanceConfig::where('parent_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'base_amount' => 'nullable|numeric|min:1',
            'amount' => 'nullable|numeric|min:1',
            'frequency' => 'nullable|in:weekly,monthly',
            'next_payment_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['amount'])) {
            $validated['base_amount'] = $validated['amount'];
        }

        $allowance->update($validated);
        $allowance->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Mesada atualizada com sucesso.',
            'data' => $this->transformAllowance($allowance),
        ], 200);
    }

    /**
     * Elimina / desativa a configuração de mesada.
     */
        #[OA\Delete(
        path: "/api/v1/finance/allowances/{id}",
        summary: "Finance Allowances {id} (DELETE)",
        tags: ["Motor Financeiro & Saldos"],
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

        $allowance = AllowanceConfig::where('parent_id', $user->id)->findOrFail($id);
        $allowance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Configuração de mesada eliminada.',
        ], 200);
    }

    /**
     * POST /api/v1/allowances/{id}/send-now - Envio Imediato de Mesada Familiar
     */
        #[OA\Post(
        path: "/api/v1/parent/allowances/{id}/send-now",
        summary: "Disparar Pagamento Imediato de Mesada",
        tags: ["Família, Mesadas & Parent Gate"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function sendNow(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $allowance = AllowanceConfig::where('parent_id', $user->id)->findOrFail($id);

        $result = DB::transaction(function () use ($allowance, $user, $request) {
            $allowanceLocked = AllowanceConfig::lockForUpdate()->findOrFail($allowance->id);
            $child = ChildProfile::lockForUpdate()->findOrFail($allowanceLocked->child_profile_id);

            $wallet = Wallet::firstOrCreate(
                [
                    'walletable_id' => $child->id,
                    'walletable_type' => ChildProfile::class,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $child->tenant_id,
                    'currency' => 'Kz',
                    'balance' => 0,
                    'is_frozen' => false,
                ]
            );

            $baseAmount = (float) $allowanceLocked->base_amount;
            $bonusApplied = (float) ($request->bonus ?? 50.00);
            $totalAmount = $baseAmount + $bonusApplied;

            $idempotencyKey = 'ALW-SENDNOW-' . $allowanceLocked->id . '-' . Str::random(8);

            LedgerEntry::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'credit',
                'entry_type' => 'allowance',
                'amount' => $totalAmount,
                'description' => 'Envio Imediato de Mesada Familiar com Bônus',
                'metadata' => [
                    'allowance_config_id' => $allowanceLocked->id,
                    'base_amount' => $baseAmount,
                    'bonus_applied' => $bonusApplied,
                    'sent_by' => $user->id,
                ],
                'created_at' => now(),
            ]);

            $wallet->balance = (float) $wallet->balance + $totalAmount;
            $wallet->save();

            return [
                'allowance_id' => $allowanceLocked->id,
                'amount_sent' => $baseAmount,
                'bonus_applied' => $bonusApplied,
                'new_balance' => (float) $wallet->balance,
                'sent_at' => now()->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $result,
        ], 200);
    }

    /**
     * Executa o pagamento pontual da mesada (Process Payout).
     */
        #[OA\Post(
        path: "/api/v1/finance/allowances/{id}/process",
        summary: "Disparar Pagamento Imediato de Mesada",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function process(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $allowance = AllowanceConfig::where('parent_id', $user->id)->findOrFail($id);

        $result = DB::transaction(function () use ($allowance, $user) {
            $allowanceLocked = AllowanceConfig::lockForUpdate()->findOrFail($allowance->id);
            $child = ChildProfile::lockForUpdate()->findOrFail($allowanceLocked->child_profile_id);

            $wallet = Wallet::where('walletable_id', $child->id)
                ->where('walletable_type', ChildProfile::class)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'walletable_id' => $child->id,
                    'walletable_type' => ChildProfile::class,
                    'tenant_id' => $child->tenant_id,
                    'currency' => 'KVC',
                    'balance' => 0,
                ]);
            }

            $amount = (float) $allowanceLocked->base_amount;
            $idempotencyKey = 'ALW-' . $allowanceLocked->id . '-' . Str::random(8);

            // Registo no livro-razão (LedgerEntry)
            LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'credit',
                'entry_type' => 'allowance',
                'amount' => $amount,
                'description' => 'Pagamento de mesada programada (' . ucfirst($allowanceLocked->frequency) . ')',
                'metadata' => [
                    'allowance_config_id' => $allowanceLocked->id,
                    'paid_by' => $user->id,
                ],
            ]);

            $wallet->balance = (float) $wallet->balance + $amount;
            $wallet->save();

            // Avançar a data da próxima mesada
            $currentNextDate = Carbon::parse($allowanceLocked->next_payment_date);
            $newNextDate = $allowanceLocked->frequency === 'weekly'
                ? $currentNextDate->addWeek()
                : $currentNextDate->addMonth();

            $allowanceLocked->next_payment_date = $newNextDate->format('Y-m-d');
            $allowanceLocked->save();

            return [
                'allowance' => $allowanceLocked->fresh('child:id,name,avatar'),
                'amount_paid' => $amount,
                'new_wallet_balance' => (float) $wallet->balance,
                'next_payment_date' => $allowanceLocked->next_payment_date,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Mesada de ' . $result['amount_paid'] . ' KVC paga com sucesso!',
            'data' => $this->transformAllowance($result['allowance']),
            'summary' => $result,
        ], 200);
    }

    /**
     * Transforma AllowanceConfig para o formato do Frontend.
     */
    private function transformAllowance(AllowanceConfig $config): array
    {
        return [
            'id' => $config->id,
            'child_id' => $config->child_profile_id,
            'child_profile_id' => $config->child_profile_id,
            'parent_id' => $config->parent_id,
            'amount' => (float) $config->base_amount,
            'base_amount' => (float) $config->base_amount,
            'frequency' => $config->frequency,
            'next_payment_date' => Carbon::parse($config->next_payment_date)->format('Y-m-d'),
            'is_active' => (bool) $config->is_active,
            'child' => $config->child ? [
                'id' => $config->child->id,
                'name' => $config->child->name,
                'avatar' => $config->child->avatar,
            ] : null,
            'created_at' => $config->created_at?->toISOString(),
            'updated_at' => $config->updated_at?->toISOString(),
        ];
    }
}
