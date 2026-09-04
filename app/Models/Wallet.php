<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'walletable_id',
        'walletable_type',
        'balance',
        'currency',
        'is_frozen',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_frozen' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relação: Pertence a Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relação Polymorphic: Proprietário (User ou ChildProfile)
     */
    public function walletable()
    {
        return $this->morphTo();
    }

    /**
     * Relação: Muitas Entradas no Ledger
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Relação: Muitas Risk Flags
     */
    public function riskFlags(): HasMany
    {
        return $this->hasMany(RiskFlag::class);
    }

    /**
     * Calcula saldo dinâmico a partir do ledger
     */
    public function calculateBalance(): float
    {
        $credits = $this->ledgerEntries()
            ->where('direction', 'credit')
            ->sum('amount');

        $debits = $this->ledgerEntries()
            ->where('direction', 'debit')
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * Verifica se carteira pode ser usada
     */
    public function canBeUsed(): bool
    {
        return !$this->is_frozen;
    }
}
