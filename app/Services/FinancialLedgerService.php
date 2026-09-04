<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\LedgerEntry;
use App\Models\Lesson;
use App\Models\Partner;
use App\Models\PartnerProgram;
use App\Models\Task;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FinancialLedgerService
{
    /**
     * Executa crédito atômico em uma carteira e gera registro imutável no ledger.
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        string $entryType,
        string $description,
        ?array $metadata = null,
        ?string $idempotencyKey = null
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O montante de crédito deve ser estritamente positivo.');
        }

        return DB::transaction(function () use ($wallet, $amount, $entryType, $description, $metadata, $idempotencyKey) {
            // Lock para concorrência
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (!$lockedWallet->canBeUsed()) {
                throw new RuntimeException('Operação rejeitada: a carteira encontra-se congelada.');
            }

            if ($idempotencyKey) {
                $existing = LedgerEntry::where('wallet_id', $lockedWallet->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $entry = LedgerEntry::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $lockedWallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'credit',
                'entry_type' => $entryType,
                'amount' => $amount,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            $lockedWallet->balance = (float) $lockedWallet->balance + $amount;
            $lockedWallet->save();

            return $entry;
        });
    }

    /**
     * Executa débito atômico em uma carteira e gera registro imutável no ledger.
     */
    public function debit(
        Wallet $wallet,
        float $amount,
        string $entryType,
        string $description,
        ?array $metadata = null,
        ?string $idempotencyKey = null
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O montante de débito deve ser estritamente positivo.');
        }

        return DB::transaction(function () use ($wallet, $amount, $entryType, $description, $metadata, $idempotencyKey) {
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (!$lockedWallet->canBeUsed()) {
                throw new RuntimeException('Operação rejeitada: a carteira encontra-se congelada.');
            }

            if ((float) $lockedWallet->balance < $amount) {
                throw new RuntimeException("Saldo insuficiente na carteira. Saldo atual: {$lockedWallet->balance}, Solicitado: {$amount}");
            }

            if ($idempotencyKey) {
                $existing = LedgerEntry::where('wallet_id', $lockedWallet->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $entry = LedgerEntry::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $lockedWallet->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'debit',
                'entry_type' => $entryType,
                'amount' => $amount,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            $lockedWallet->balance = (float) $lockedWallet->balance - $amount;
            $lockedWallet->save();

            return $entry;
        });
    }

    /**
     * Recompensa de conclusão de lição/missão pedagógica aprovada.
     */
    public function rewardLessonCompletion(
        $recipient,
        Lesson $lesson,
        ?string $evaluatorId = null
    ): array {
        $wallet = $this->getOrCreateWalletForRecipient($recipient);

        $coinsReward = (float) ($lesson->coins_reward ?? $lesson->xp_reward ?? 10);
        $pointsReward = (int) ($lesson->kiva_points_reward ?? $lesson->xp_reward ?? 50);

        $idempotencyKey = "lesson_reward_{$lesson->id}_{$recipient->id}";

        $entry = $this->credit(
            wallet: $wallet,
            amount: $coinsReward,
            entryType: 'reward_academic',
            description: "Recompensa pela conclusão da lição: {$lesson->title}",
            metadata: [
                'lesson_id' => $lesson->id,
                'points_reward' => $pointsReward,
                'evaluator_id' => $evaluatorId,
                'recipient_type' => get_class($recipient),
                'recipient_id' => $recipient->id,
            ],
            idempotencyKey: $idempotencyKey
        );

        return [
            'transaction_id' => $entry->id,
            'kiva_coins_credited' => $coinsReward,
            'kiva_points_credited' => $pointsReward,
            'student_wallet_balance' => (float) $wallet->fresh()->balance,
        ];
    }

    /**
     * Recompensa de aprovação parental de tarefa doméstica.
     */
    public function rewardTaskApproval(Task $task, User $parent): array
    {
        $child = $task->child;
        if (!$child) {
            throw new RuntimeException('A tarefa não possui perfil de criança associado.');
        }

        $wallet = $this->getOrCreateWalletForRecipient($child);
        $rewardAmount = (float) ($task->reward_amount ?? $task->reward ?? 0);

        $idempotencyKey = "task_reward_{$task->id}";

        $entry = $this->credit(
            wallet: $wallet,
            amount: $rewardAmount,
            entryType: 'reward_task',
            description: "Recompensa pela tarefa aprovada: {$task->title}",
            metadata: [
                'task_id' => $task->id,
                'approved_by_user_id' => $parent->id,
                'child_id' => $child->id,
            ],
            idempotencyKey: $idempotencyKey
        );

        return [
            'transaction_id' => $entry->id,
            'amount_credited' => $rewardAmount,
            'new_balance' => (float) $wallet->fresh()->balance,
        ];
    }

    /**
     * Desembolso corporativo de fundos do parceiro para recompensas.
     */
    public function disbursePartnerFunds(
        PartnerProgram $program,
        float $amount,
        string $reason,
        ?array $metadata = null
    ): array {
        $partner = $program->partner;
        if (!$partner) {
            throw new RuntimeException('Programa sem parceiro corporativo associado.');
        }

        $wallet = $this->getOrCreateWalletForRecipient($partner);

        $entry = $this->debit(
            wallet: $wallet,
            amount: $amount,
            entryType: 'partner_disbursement',
            description: "Desembolso do programa {$program->title}: {$reason}",
            metadata: array_merge($metadata ?? [], [
                'program_id' => $program->id,
                'partner_id' => $partner->id,
            ]),
            idempotencyKey: (string) Str::uuid()
        );

        return [
            'transaction_id' => $entry->id,
            'amount_disbursed' => $amount,
            'partner_remaining_balance' => (float) $wallet->fresh()->balance,
        ];
    }

    /**
     * Top-up / Financiamento da carteira de um parceiro.
     */
    public function topUpPartnerWallet(
        Partner $partner,
        float $amount,
        string $paymentMethod = 'bank_transfer',
        ?string $referenceProof = null
    ): array {
        $wallet = $this->getOrCreateWalletForRecipient($partner);

        $idempotencyKey = "topup_partner_{$partner->id}_" . Str::random(10);

        $entry = $this->credit(
            wallet: $wallet,
            amount: $amount,
            entryType: 'partner_top_up',
            description: "Carregamento de orçamento corporativo via {$paymentMethod}",
            metadata: [
                'partner_id' => $partner->id,
                'payment_method' => $paymentMethod,
                'reference_proof' => $referenceProof,
            ],
            idempotencyKey: $idempotencyKey
        );

        if (isset($partner->budget_available)) {
            $partner->budget_available = (float) $partner->budget_available + $amount;
            $partner->budget_total = (float) ($partner->budget_total ?? 0) + $amount;
            $partner->save();
        }

        return [
            'partner_id' => $partner->id,
            'transaction_id' => $entry->id,
            'amount_credited' => $amount,
            'new_budget_available' => (float) $wallet->fresh()->balance,
            'status' => 'completed',
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Localiza ou cria a Wallet associada ao modelo destinatário (ChildProfile, User ou Partner).
     */
    public function getOrCreateWalletForRecipient($recipient): Wallet
    {
        $wallet = null;

        if (method_exists($recipient, 'wallet') && $recipient->wallet) {
            $wallet = $recipient->wallet;
        }

        if (!$wallet) {
            $tenantId = $recipient->tenant_id ?? null;

            $wallet = Wallet::firstOrCreate(
                [
                    'walletable_id' => $recipient->id,
                    'walletable_type' => get_class($recipient),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'balance' => 0.00,
                    'currency' => 'Kz',
                    'is_frozen' => false,
                ]
            );
        }

        return $wallet;
    }
}
