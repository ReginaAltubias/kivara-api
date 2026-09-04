<?php

namespace App\Http\Controllers\Api\Partner;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PartnerWalletController extends Controller
{
    const EXCHANGE_RATE_AOA_TO_KVC = 10; // 1 AOA = 10 KVC

    /**
     * GET /api/v1/partner/wallet
     * 
     * Retorna o saldo cativo do parceiro em KVC e AOA, bem como o histórico de movimentos.
     */
        #[OA\Get(
        path: "/api/v1/partner/wallet",
        summary: "Partner Wallet (GET)",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError")
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        // Obter ou inicializar a carteira KVC do parceiro
        $wallet = Wallet::firstOrCreate(
            [
                'walletable_id'   => $partner->id,
                'walletable_type' => Partner::class,
                'currency'        => 'KVC',
            ],
            [
                'balance'   => 0,
                'is_frozen' => false,
            ]
        );

        // Movimentos da carteira (extrato ledger)
        $ledgerEntries = LedgerEntry::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(function ($entry) {
                return [
                    'id'          => $entry->id,
                    'direction'   => $entry->direction,
                    'entry_type'  => $entry->entry_type,
                    'amount_kvc'  => (float) $entry->amount,
                    'amount_aoa'  => round((float) $entry->amount / self::EXCHANGE_RATE_AOA_TO_KVC, 2),
                    'description' => $entry->description,
                    'metadata'    => $entry->metadata,
                    'created_at'  => $entry->created_at?->toISOString(),
                ];
            });

        $totalDepositedKvc = LedgerEntry::where('wallet_id', $wallet->id)
            ->where('direction', 'credit')
            ->where('entry_type', 'top_up')
            ->sum('amount');

        $totalDistributedKvc = LedgerEntry::where('wallet_id', $wallet->id)
            ->where('direction', 'debit')
            ->sum('amount');

        $balanceKvc = (float) $wallet->balance;
        $balanceAoa = round($balanceKvc / self::EXCHANGE_RATE_AOA_TO_KVC, 2);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'partner_id'            => $partner->id,
                'partner_name'          => $partner->name,
                'wallet_id'             => $wallet->id,
                'balance_kvc'           => $balanceKvc,
                'balance_aoa'           => $balanceAoa,
                'exchange_rate'         => [
                    'rate'        => self::EXCHANGE_RATE_AOA_TO_KVC,
                    'description' => '1 AOA = ' . self::EXCHANGE_RATE_AOA_TO_KVC . ' KVC (Kiva Coins)',
                ],
                'total_deposited_aoa'   => round($totalDepositedKvc / self::EXCHANGE_RATE_AOA_TO_KVC, 2),
                'total_distributed_kvc' => (float) $totalDistributedKvc,
                'transactions'          => $ledgerEntries,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/partner/wallet/top-up
     * 
     * Inicia o carregamento de saldo B2B em AOA com conversão para KVC.
     */
        #[OA\Post(
        path: "/api/v1/partner/wallet/top-up",
        summary: "Partner Wallet Top Up (POST)",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function topUp(Request $request): JsonResponse
    {
        $partner = $this->getAuthenticatedPartner($request);

        $validated = $request->validate([
            'amount_aoa'     => 'required|numeric|min:1000',
            'payment_method' => 'required|in:multicaixa,reference,mcx_express',
            'phone'          => 'nullable|string|max:20',
        ]);

        $amountAoa = (float) $validated['amount_aoa'];
        $kvcAmount = $amountAoa * self::EXCHANGE_RATE_AOA_TO_KVC;
        $paymentMethod = $validated['payment_method'];

        // Obter carteira KVC
        $wallet = Wallet::firstOrCreate(
            [
                'walletable_id'   => $partner->id,
                'walletable_type' => Partner::class,
                'currency'        => 'KVC',
            ],
            [
                'balance'   => 0,
                'is_frozen' => false,
            ]
        );

        $reference = null;
        $paymentDetails = [];

        if ($paymentMethod === 'reference') {
            $entity = '00543'; // Entidade KIVARA
            $refNumber = (string) mt_rand(100000000, 999999999);
            $reference = "{$entity}-{$refNumber}";
            $paymentDetails = [
                'entity'     => $entity,
                'reference'  => $refNumber,
                'amount_aoa' => $amountAoa,
                'amount_kvc' => $kvcAmount,
                'expires_at' => now()->addDays(3)->toISOString(),
            ];
        } else {
            $phone = $validated['phone'] ?? $request->user()->phone ?? '923000000';
            $transactionId = 'MCX-B2B-' . strtoupper(Str::random(8));
            $reference = $transactionId;
            $paymentDetails = [
                'phone'          => $phone,
                'transaction_id' => $transactionId,
                'amount_aoa'     => $amountAoa,
                'amount_kvc'     => $kvcAmount,
                'prompt_sent'    => true,
                'message'        => 'Notificação de carregamento emitida para o terminal Multicaixa Express.',
            ];
        }

        // Criar PaymentIntent para auditar o depósito
        $intent = PaymentIntent::create([
            'wallet_id'          => $wallet->id,
            'user_id'            => $request->user()->id,
            'amount'             => $amountAoa,
            'currency'           => 'AOA',
            'payment_method'     => $paymentMethod,
            'phone'              => $validated['phone'] ?? null,
            'status'             => 'pending',
            'provider_reference' => $reference,
            'metadata'           => [
                'partner_id'     => $partner->id,
                'amount_aoa'     => $amountAoa,
                'amount_kvc'     => $kvcAmount,
                'exchange_rate'  => self::EXCHANGE_RATE_AOA_TO_KVC,
                'payment_data'   => $paymentDetails,
            ],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Pedido de carregamento gerado. Serão creditados " . number_format($kvcAmount, 0, ',', '.') . " KVC após confirmação bancária.",
            'data'    => [
                'payment_intent_id'  => $intent->id,
                'amount_aoa'         => $amountAoa,
                'amount_kvc'         => $kvcAmount,
                'payment_method'     => $paymentMethod,
                'provider_reference' => $reference,
                'payment_details'    => $paymentDetails,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/partner/wallet/webhook
     * 
     * Callback transacional do gateway para liquidação de carregamento B2B.
     */
        #[OA\Post(
        path: "/api/v1/partner/wallet/webhook",
        summary: "Partner Wallet Webhook (POST)",
        tags: ["B2B - Parceiros & Patrocinadores"],
        responses: [
            new OA\Response(response: 200, description: "Operação realizada com sucesso."),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Acesso não autorizado para o perfil do utilizador."),
            new OA\Response(response: 422, ref: "#/components/responses/ValidationError"),
            new OA\Response(response: 201, description: "Recurso criado com sucesso.")
        ]
    )]
    public function webhook(Request $request): JsonResponse
    {
        Log::info('Partner Wallet Webhook Received', ['payload' => $request->all()]);

        $reference = $request->input('provider_reference')
                  ?? $request->input('payment_reference')
                  ?? $request->input('transaction_id')
                  ?? $request->input('reference')
                  ?? $request->input('intent_id');

        if (!$reference) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Referência de pagamento não fornecida.',
            ], 422);
        }

        $result = DB::transaction(function () use ($reference) {
            // 1. Bloqueio pessimista no PostgreSQL para garantir consistência
            $intent = PaymentIntent::where('provider_reference', $reference)
                ->orWhere('id', $reference)
                ->lockForUpdate()
                ->first();

            if (!$intent) {
                return [
                    'success' => false,
                    'code'    => 404,
                    'message' => 'Intenção de pagamento não encontrada.',
                ];
            }

            // Idempotência
            if ($intent->status === 'completed') {
                return [
                    'success' => true,
                    'code'    => 200,
                    'message' => 'Carregamento já processado anteriormente.',
                ];
            }

            $metadata = $intent->metadata ?? [];
            $amountAoa = (float) ($metadata['amount_aoa'] ?? $intent->amount);
            $kvcAmount = (float) ($metadata['amount_kvc'] ?? ($amountAoa * self::EXCHANGE_RATE_AOA_TO_KVC));
            $partnerId = $metadata['partner_id'] ?? null;

            $wallet = Wallet::lockForUpdate()->find($intent->wallet_id);

            if (!$wallet) {
                return [
                    'success' => false,
                    'code'    => 404,
                    'message' => 'Carteira do parceiro não encontrada.',
                ];
            }

            $idempotencyKey = "partner_topup:{$intent->id}";

            // 2. Registar no Ledger (append-only)
            $ledgerEntry = LedgerEntry::create([
                'wallet_id'       => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction'       => 'credit',
                'entry_type'      => 'top_up',
                'amount'          => $kvcAmount,
                'description'     => "Carregamento B2B de Orçamento (" . number_format($amountAoa, 2, ',', '.') . " AOA)",
                'metadata'        => [
                    'payment_intent_id' => $intent->id,
                    'partner_id'        => $partnerId,
                    'amount_aoa'        => $amountAoa,
                    'exchange_rate'     => self::EXCHANGE_RATE_AOA_TO_KVC,
                ],
                'created_at'      => now(),
            ]);

            // 3. Creditar saldo na carteira
            $wallet->increment('balance', $kvcAmount);

            // 4. Concluir PaymentIntent
            $intent->update([
                'status'          => 'completed',
                'ledger_entry_id' => $ledgerEntry->id,
                'completed_at'    => now(),
            ]);

            return [
                'success' => true,
                'code'    => 200,
                'message' => "Carregamento de {$kvcAmount} KVC creditado com sucesso na carteira do parceiro.",
            ];
        });

        return response()->json([
            'status'  => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ], $result['code']);
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

        // Se o utilizador tiver relação partner direta
        if ($user->partner) {
            return $user->partner;
        }

        // Se for admin a gerir o primeiro parceiro de teste
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
