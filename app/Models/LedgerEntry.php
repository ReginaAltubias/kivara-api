<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasUuids;

    public $timestamps = false; // Apenas created_at, nunca updated_at

    protected $fillable = [
        'wallet_id',
        'idempotency_key',
        'direction',
        'entry_type',
        'amount',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    /**
     * Relação: Pertence a Wallet
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * ⚠️ APPEND-ONLY: Nunca atualizar ou deletar!
     * O modelo bloqueia estas operações
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('LedgerEntry é append-only. Não pode ser atualizado.');
    }

    public function delete()
    {
        throw new \Exception('LedgerEntry é append-only. Não pode ser deletado.');
    }

    public function forceDelete()
    {
        throw new \Exception('LedgerEntry é append-only. Não pode ser forçadamente deletado.');
    }
}
