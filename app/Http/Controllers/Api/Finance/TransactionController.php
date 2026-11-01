<?php

namespace App\Http\Controllers\Api\Finance;

use OpenApi\Attributes as OA;

use App\Models\Wallet;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * GET /finance/transactions - Listar Transações
     */
        #[OA\Get(
        path: "/api/v1/finance/transactions",
        summary: "Finance Transactions (GET)",
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
        $perPage = $request->query('per_page', 20);
        $page = $request->query('page', 1);

        $user = $request->user();
        $visibleWallets = Wallet::query()->when($user->role !== 'admin', fn ($q) => $q->ownedBy($user))->select('wallets.id');

        $transactions = LedgerEntry::query()
            ->whereIn('wallet_id', $visibleWallets)
            ->when($request->filled('wallet_id'), fn($q) => $q->where('wallet_id', $request->wallet_id))
            ->when($request->filled('entry_type'), fn($q) => $q->where('entry_type', $request->entry_type))
            ->when($request->filled('direction'), fn($q) => $q->where('direction', $request->direction))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ], 200);
    }

    /**
     * POST /finance/transactions - Criar Transação (Idempotent)
     */
        #[OA\Post(
        path: "/api/v1/finance/transactions",
        summary: "Finance Transactions (POST)",
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
        $request->validate([
            'wallet_id' => 'required|uuid|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'direction' => 'required|in:credit,debit',
            'entry_type' => 'required|in:allowance,task_reward,mission_reward,purchase,vault_deposit,vault_interest,donation,adjustment',
            'description' => 'required|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        // Creditos manuais so o admin; o dono so regista debitos de compra/doacao/cofre na sua carteira.
        if (!$isAdmin && ($request->direction !== 'debit' || !in_array($request->entry_type, ['purchase', 'donation', 'vault_deposit'], true))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Operação não permitida: apenas débitos de compra, doação ou cofre.',
            ], 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if (!$idempotencyKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Header Idempotency-Key é obrigatório.',
            ], 400);
        }

        // ====================================================================
        // Verificar se já existe transação com esta chave
        // ====================================================================
        $existing = LedgerEntry::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $mine = $isAdmin || Wallet::ownedBy($user)->whereKey($existing->wallet_id)->exists();
            if (!$mine) {
                return response()->json(['status' => 'error', 'message' => 'Chave de idempotência já utilizada.'], 409);
            }

            return response()->json([
                'status' => 'success',
                'data' => $existing,
                'is_duplicate' => true,
            ], 200);
        }

        // ====================================================================
        // Pessimistic Locking: Bloqueia a wallet para evitar race conditions
        // ====================================================================
        $wallet = DB::transaction(function () use ($request, $idempotencyKey, $user, $isAdmin) {
            $wallet = Wallet::query()
                ->when(!$isAdmin, fn ($q) => $q->ownedBy($user))
                ->lockForUpdate()
                ->findOrFail($request->wallet_id);

            // Validar se wallet está congelada
            if ($wallet->is_frozen) {
                throw new \Exception('Wallet está congelada.');
            }

            // Validar saldo se for débito
            if ($request->direction === 'debit') {
                $currentBalance = $wallet->calculateBalance();
                if ($currentBalance < $request->amount) {
                    throw new \Exception('Saldo insuficiente: ' . $currentBalance . ' < ' . $request->amount);
                }
            }

            // Criar entrada no ledger
            $ledgerEntry = LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => $request->direction,
                'entry_type' => $request->entry_type,
                'amount' => $request->amount,
                'description' => $request->description,
                'metadata' => $request->metadata,
            ]);

            $wallet->balance = round((float) $wallet->balance + ($request->direction === 'credit' ? 1 : -1) * (float) $request->amount, 2);
            $wallet->save();

            return [
                'entry' => $ledgerEntry,
                'balance' => $wallet->calculateBalance(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $wallet['entry']->id,
                'amount' => $wallet['entry']->amount,
                'direction' => $wallet['entry']->direction,
                'entry_type' => $wallet['entry']->entry_type,
                'description' => $wallet['entry']->description,
                'balance_after' => $wallet['balance'],
                'created_at' => $wallet['entry']->created_at,
            ],
        ], 201);
    }
}
