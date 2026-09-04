<?php

namespace App\Http\Controllers\Api\Finance;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\DreamVault;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VaultController extends Controller
{
    /**
     * Lista os cofres de sonhos (Dream Vaults) dos filhos do utilizador.
     */
        #[OA\Get(
        path: "/api/v1/vaults",
        summary: "Listar Cofres dos Sonhos",
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

        $query = DreamVault::query()
            ->whereHas('child', function ($q) use ($user) {
                // Se for parent, filtra pelos seus filhos; se for o próprio child/teen autenticado, filtra por ele
                $q->where('parent_id', $user->id)
                    ->orWhere('id', $user->id);
            })
            ->with(['child:id,name,avatar,parent_id'])
            ->orderByDesc('created_at');

        if ($request->filled('child_id') || $request->filled('child_profile_id')) {
            $childId = $request->input('child_id') ?? $request->input('child_profile_id');
            $query->where('child_profile_id', $childId);
        }

        $vaults = $query->get()->map(fn (DreamVault $vault) => $this->transformVault($vault));

        return response()->json([
            'status' => 'success',
            'data' => $vaults,
        ], 200);
    }

    /**
     * Cria um novo cofre de sonhos para a criança.
     */
        #[OA\Post(
        path: "/api/v1/vaults",
        summary: "Criar Novo Cofre dos Sonhos",
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
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:1',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'target_date' => 'nullable|date|after:today',
            'deadline' => 'nullable|date|after:today',
        ]);

        $childId = $validated['child_profile_id'] ?? $validated['child_id'];

        if (!$childId) {
            $child = ChildProfile::where('parent_id', $user->id)->first();
            if (!$child) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nenhum perfil de filho encontrado.',
                ], 422);
            }
            $childId = $child->id;
        }

        $targetDate = $validated['target_date'] ?? $validated['deadline'] ?? null;

        $vault = DreamVault::create([
            'child_profile_id' => $childId,
            'name' => $validated['name'],
            'target_amount' => $validated['target_amount'],
            'current_amount' => 0,
            'icon' => $validated['icon'] ?? '🎯',
            'description' => $validated['description'] ?? null,
            'interest_rate' => $validated['interest_rate'] ?? 0,
            'is_active' => true,
            'target_date' => $targetDate,
        ]);

        $vault->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Cofre de sonhos criado com sucesso!',
            'data' => $this->transformVault($vault),
        ], 201);
    }

    /**
     * Detalhes de um cofre de sonhos.
     */
        #[OA\Get(
        path: "/api/v1/vaults/{id}",
        summary: "Obter Detalhes do Cofre dos Sonhos",
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

        $vault = DreamVault::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })
        ->with('child:id,name,avatar')
        ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformVault($vault),
        ], 200);
    }

    /**
     * Atualiza dados de um cofre.
     */
        #[OA\Patch(
        path: "/api/v1/vaults/{id}",
        summary: "Atualizar Cofre dos Sonhos",
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

        $vault = DreamVault::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'target_amount' => 'nullable|numeric|min:1',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'target_date' => 'nullable|date',
            'deadline' => 'nullable|date',
        ]);

        if (isset($validated['deadline'])) {
            $validated['target_date'] = $validated['deadline'];
        }

        $vault->update($validated);
        $vault->load('child:id,name,avatar');

        return response()->json([
            'status' => 'success',
            'message' => 'Cofre atualizado com sucesso.',
            'data' => $this->transformVault($vault),
        ], 200);
    }

    /**
     * Elimina um cofre de sonhos e devolve o saldo acumulado para a carteira da criança.
     */
        #[OA\Delete(
        path: "/api/v1/vaults/{id}",
        summary: "Remover / Cancelar Cofre dos Sonhos",
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

        $vault = DreamVault::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        DB::transaction(function () use ($vault, $user) {
            $currentAmount = (float) $vault->current_amount;

            if ($currentAmount > 0) {
                $child = ChildProfile::lockForUpdate()->findOrFail($vault->child_profile_id);
                $wallet = Wallet::where('walletable_id', $child->id)
                    ->where('walletable_type', ChildProfile::class)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $idempotencyKey = 'VAULT-DEL-' . $vault->id . '-' . Str::random(8);

                    LedgerEntry::create([
                        'wallet_id' => $wallet->id,
                        'idempotency_key' => $idempotencyKey,
                        'direction' => 'credit',
                        'entry_type' => 'adjustment',
                        'amount' => $currentAmount,
                        'description' => 'Devolução de fundos do cofre encerrado: ' . $vault->name,
                        'metadata' => [
                            'vault_id' => $vault->id,
                            'vault_name' => $vault->name,
                            'deleted_by' => $user->id,
                        ],
                    ]);

                    $wallet->balance = (float) $wallet->balance + $currentAmount;
                    $wallet->save();
                }
            }

            $vault->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Cofre encerrado e saldo devolvido com sucesso.',
        ], 200);
    }

    /**
     * Deposita fundos da carteira da criança para o Cofre de Sonhos.
     */
        #[OA\Post(
        path: "/api/v1/vaults/{id}/deposit",
        summary: "Criar Novo Cofre dos Sonhos",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function deposit(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $vault = DreamVault::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = (float) $validated['amount'];

        $result = DB::transaction(function () use ($vault, $amount, $user) {
            $vaultLocked = DreamVault::lockForUpdate()->findOrFail($vault->id);
            $child = ChildProfile::lockForUpdate()->findOrFail($vaultLocked->child_profile_id);

            $wallet = Wallet::where('walletable_id', $child->id)
                ->where('walletable_type', ChildProfile::class)
                ->lockForUpdate()
                ->first();

            if (!$wallet || (float) $wallet->balance < $amount) {
                abort(422, 'Saldo insuficiente na carteira para realizar este depósito no cofre.');
            }

            $idempotencyKey = 'VAULT-DEP-' . $vaultLocked->id . '-' . Str::random(8);

            // Débito no Ledger da Carteira
            LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'debit',
                'entry_type' => 'vault_deposit',
                'amount' => $amount,
                'description' => 'Depósito no cofre: ' . $vaultLocked->name,
                'metadata' => [
                    'vault_id' => $vaultLocked->id,
                    'vault_name' => $vaultLocked->name,
                    'deposited_by' => $user->id,
                ],
            ]);

            $wallet->balance = (float) $wallet->balance - $amount;
            $wallet->save();

            $vaultLocked->current_amount = (float) $vaultLocked->current_amount + $amount;
            $vaultLocked->save();

            return [
                'vault' => $vaultLocked->fresh('child:id,name,avatar'),
                'new_wallet_balance' => (float) $wallet->balance,
                'deposited_amount' => $amount,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Depósito de ' . $result['deposited_amount'] . ' KVC realizado com sucesso no cofre.',
            'data' => $this->transformVault($result['vault']),
            'wallet_balance' => $result['new_wallet_balance'],
        ], 200);
    }

    /**
     * Resgata fundos do cofre de volta para a carteira da criança (com bónus se concluído).
     */
        #[OA\Post(
        path: "/api/v1/vaults/{id}/withdraw",
        summary: "Criar Novo Cofre dos Sonhos",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function withdraw(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $vault = DreamVault::whereHas('child', function ($q) use ($user) {
            $q->where('parent_id', $user->id)->orWhere('id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
        ]);

        $result = DB::transaction(function () use ($vault, $validated, $user) {
            $vaultLocked = DreamVault::lockForUpdate()->findOrFail($vault->id);
            $currentAmount = (float) $vaultLocked->current_amount;

            $amountToWithdraw = isset($validated['amount']) ? min((float) $validated['amount'], $currentAmount) : $currentAmount;

            if ($amountToWithdraw <= 0) {
                abort(422, 'O cofre não tem saldo disponível para resgate.');
            }

            $child = ChildProfile::lockForUpdate()->findOrFail($vaultLocked->child_profile_id);
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

            // Se atingiu a meta do cofre e tem taxa de incentivo/juros parental, calcula bónus
            $bonusAmount = 0.0;
            if ($currentAmount >= (float) $vaultLocked->target_amount && (float) $vaultLocked->interest_rate > 0) {
                $bonusAmount = round($amountToWithdraw * ((float) $vaultLocked->interest_rate / 100), 2);
            }

            $totalCredit = $amountToWithdraw + $bonusAmount;

            $idempotencyKey = 'VAULT-WDR-' . $vaultLocked->id . '-' . Str::random(8);

            LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'credit',
                'entry_type' => 'adjustment',
                'amount' => $totalCredit,
                'description' => 'Resgate do cofre de sonhos: ' . $vaultLocked->name . ($bonusAmount > 0 ? " (inclui bónus de {$bonusAmount} KVC)" : ''),
                'metadata' => [
                    'vault_id' => $vaultLocked->id,
                    'principal' => $amountToWithdraw,
                    'bonus' => $bonusAmount,
                    'withdrawn_by' => $user->id,
                ],
            ]);

            $wallet->balance = (float) $wallet->balance + $totalCredit;
            $wallet->save();

            $vaultLocked->current_amount = max(0, $currentAmount - $amountToWithdraw);
            $vaultLocked->save();

            return [
                'vault' => $vaultLocked->fresh('child:id,name,avatar'),
                'withdrawn_amount' => $amountToWithdraw,
                'bonus_amount' => $bonusAmount,
                'total_credited' => $totalCredit,
                'new_wallet_balance' => (float) $wallet->balance,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Resgate efetuado com sucesso! ' . $result['total_credited'] . ' KVC creditados na carteira.',
            'data' => $this->transformVault($result['vault']),
            'summary' => $result,
        ], 200);
    }

    /**
     * Formatação do modelo DreamVault.
     */
    private function transformVault(DreamVault $vault): array
    {
        $target = (float) $vault->target_amount;
        $current = (float) $vault->current_amount;
        $percent = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0;

        return [
            'id' => $vault->id,
            'vault_id' => $vault->id,
            'child_id' => $vault->child_profile_id,
            'child_profile_id' => $vault->child_profile_id,
            'name' => $vault->name,
            'description' => $vault->description,
            'icon' => $vault->icon ?: '🎯',
            'target_amount' => $target,
            'current_amount' => $current,
            'current_balance' => $current,
            'interest_rate' => (float) ($vault->interest_rate ?? 0),
            'progress_percent' => $percent,
            'is_active' => (bool) $vault->is_active,
            'target_date' => $vault->target_date?->format('Y-m-d'),
            'deadline' => $vault->target_date?->format('Y-m-d'),
            'child' => $vault->child ? [
                'id' => $vault->child->id,
                'name' => $vault->child->name,
                'avatar' => $vault->child->avatar,
            ] : null,
            'created_at' => $vault->created_at?->toISOString(),
            'updated_at' => $vault->updated_at?->toISOString(),
        ];
    }
}
