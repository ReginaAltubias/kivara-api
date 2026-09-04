<?php

namespace App\Http\Controllers\Api\Finance;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\InitiateTopUpRequest;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * Saldo da carteira do utilizador autenticado.
     */
        #[OA\Get(
        path: "/api/v1/finance/wallet/balance",
        summary: "Finance Wallet Balance (GET)",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->resolveWallet($request->user());

        if ($wallet instanceof JsonResponse) {
            return $wallet;
        }

        $ledgerBalance = $wallet->calculateBalance();

        return response()->json([
            'status' => 'success',
            'data' => [
                'wallet_id' => $wallet->id,
                'balance' => (float) $wallet->balance,
                'ledger_balance' => round($ledgerBalance, 2),
                'currency' => $wallet->currency,
                'is_frozen' => $wallet->is_frozen,
            ],
        ]);
    }

    /**
     * Histórico de movimentos (ledger) da carteira.
     */
        #[OA\Get(
        path: "/api/v1/finance/wallet/history",
        summary: "Finance Wallet History (GET)",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $wallet = $this->resolveWallet($request->user());

        if ($wallet instanceof JsonResponse) {
            return $wallet;
        }

        $entries = LedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('entry_type'), fn ($q) => $q->where('entry_type', $request->string('entry_type')))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $entries->items(),
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    /**
     * Inicia top-up via Multicaixa Express (payment intent pending).
     */
        #[OA\Post(
        path: "/api/v1/finance/wallet/top-up",
        summary: "Finance Wallet Top Up (POST)",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function initiateTopUp(InitiateTopUpRequest $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->resolveWallet($user);

        if ($wallet instanceof JsonResponse) {
            return $wallet;
        }

        if (! $wallet->canBeUsed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'A carteira está congelada. Contacta o suporte KIVARA.',
            ], 423);
        }

        $validated = $request->validated();
        $providerReference = 'MCX-' . Str::upper(Str::random(12));

        // Mock gateway: "envia" pedido Multicaixa Express para o telemóvel
        Log::info('Multicaixa Express top-up initiated (mock)', [
            'user_id' => $user->id,
            'phone' => $validated['phone'],
            'amount' => $validated['amount'],
            'provider_reference' => $providerReference,
        ]);

        $intent = PaymentIntent::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'currency' => $wallet->currency ?: 'AOA',
            'payment_method' => $validated['payment_method'],
            'phone' => $validated['phone'],
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'metadata' => [
                'gateway' => 'multicaixa_express',
                'mock' => true,
                'push_requested' => true,
                'instructions' => 'Confirma o pagamento no Multicaixa Express no telemóvel indicado.',
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pedido de carregamento iniciado. Confirma no Multicaixa Express.',
            'data' => [
                'payment_intent_id' => $intent->id,
                'provider_reference' => $intent->provider_reference,
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
                'payment_method' => $intent->payment_method,
                'phone' => $intent->phone,
                'status' => $intent->status,
            ],
        ], 201);
    }

    /**
     * Webhook de liquidação (aprovação Multicaixa / gateway).
     * Credita ledger + atualiza balance de forma idempotente.
     */
        #[OA\Post(
        path: "/api/v1/finance/wallet/webhooks/multicaixa",
        summary: "Finance Wallet Webhooks Multicaixa (POST)",
        tags: ["Motor Financeiro & Saldos"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function settleTopUp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_reference' => 'required|string|exists:payment_intents,provider_reference',
            'status' => 'required|in:completed,failed',
            'gateway_payload' => 'nullable|array',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                /** @var PaymentIntent $intent */
                $intent = PaymentIntent::query()
                    ->where('provider_reference', $validated['provider_reference'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($intent->status === 'completed') {
                    return [
                        'intent' => $intent,
                        'duplicate' => true,
                        'balance' => (float) $intent->wallet->balance,
                    ];
                }

                if ($intent->status !== 'pending') {
                    throw new \RuntimeException('Payment intent não está pendente.');
                }

                if ($validated['status'] === 'failed') {
                    $intent->update([
                        'status' => 'failed',
                        'metadata' => array_merge($intent->metadata ?? [], [
                            'gateway_payload' => $validated['gateway_payload'] ?? null,
                        ]),
                    ]);

                    return [
                        'intent' => $intent->fresh(),
                        'duplicate' => false,
                        'balance' => (float) $intent->wallet->balance,
                    ];
                }

                $wallet = Wallet::query()->lockForUpdate()->findOrFail($intent->wallet_id);

                $entry = LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'idempotency_key' => 'topup:' . $intent->id,
                    'direction' => 'credit',
                    'entry_type' => 'top_up',
                    'amount' => $intent->amount,
                    'description' => 'Carregamento Multicaixa Express',
                    'metadata' => [
                        'payment_intent_id' => $intent->id,
                        'provider_reference' => $intent->provider_reference,
                        'payment_method' => $intent->payment_method,
                        'phone' => $intent->phone,
                    ],
                    'created_at' => now(),
                ]);

                $wallet->balance = round(((float) $wallet->balance) + ((float) $intent->amount), 2);
                $wallet->save();

                $intent->update([
                    'status' => 'completed',
                    'ledger_entry_id' => $entry->id,
                    'completed_at' => now(),
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'gateway_payload' => $validated['gateway_payload'] ?? null,
                    ]),
                ]);

                return [
                    'intent' => $intent->fresh(),
                    'duplicate' => false,
                    'balance' => (float) $wallet->balance,
                    'ledger_entry_id' => $entry->id,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => $result['duplicate']
                    ? 'Pagamento já liquidado anteriormente.'
                    : 'Pagamento liquidado com sucesso.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Top-up settlement failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Não foi possível liquidar o pagamento.',
            ], 500);
        }
    }

    /**
     * Resolve ou cria a carteira do utilizador no tenant correcto.
     */
    private function resolveWallet(User $user): Wallet|JsonResponse
    {
        $tenantId = $user->tenant_id ?? $user->family?->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Utilizador sem tenant associado. Não é possível aceder à carteira.',
                'error_code' => 'wallet_tenant_missing',
            ], 422);
        }

        return Wallet::query()->firstOrCreate(
            [
                'walletable_id' => $user->id,
                'walletable_type' => User::class,
                'tenant_id' => $tenantId,
            ],
            [
                'balance' => 0,
                'currency' => 'AOA',
                'is_frozen' => false,
            ]
        );
    }
}
