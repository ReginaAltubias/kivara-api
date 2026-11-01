<?php

namespace App\Support;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * Movimentos de carteira (ledger append-only + saldo materializado), sempre numa transacao
 * com a carteira bloqueada. Montantes em moeda da carteira (AOA); 1 AOA = 10 KVC.
 */
class WalletLedger
{
    public const KVC_PER_AOA = 10;

    /** Limite mensal de KVC emitidos por familia (missoes e tarefas). */
    public static function emissionLimitKvc(): int
    {
        return (int) env('KIVARA_EMISSION_LIMIT_KVC', 100000);
    }

    /**
     * @throws \DomainException saldo insuficiente / carteira congelada
     */
    public static function move(Wallet $wallet, string $direction, float $amount, string $entryType, string $description, string $idempotencyKey, array $metadata = []): LedgerEntry
    {
        return DB::transaction(function () use ($wallet, $direction, $amount, $entryType, $description, $idempotencyKey, $metadata) {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            if (! $locked->canBeUsed()) {
                throw new \DomainException('A carteira está congelada.');
            }

            if ($direction === 'debit' && (float) $locked->balance + 0.0001 < $amount) {
                throw new \DomainException('Saldo insuficiente na carteira.');
            }

            $entry = LedgerEntry::create([
                'wallet_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'direction' => $direction,
                'entry_type' => $entryType,
                'amount' => $amount,
                'description' => $description,
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);

            $locked->balance = round((float) $locked->balance + ($direction === 'credit' ? $amount : -$amount), 2);
            $locked->save();

            return $entry;
        });
    }
}
