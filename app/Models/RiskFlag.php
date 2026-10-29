<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskFlag extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'wallet_id',
        'ledger_entry_id',
        'risk_level',
        'flag_type',
        'description',
        'is_reviewed',
        'reviewed_by',
        'admin_notes',
    ];

    protected $appends = ['severity'];

    protected $casts = [
        'is_reviewed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getSeverityAttribute(): ?string
    {
        return $this->risk_level;
    }

    /**
     * Carteira alvo do alerta.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Entrada do ledger associada (opcional).
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    /**
     * Admin que reviu a flag.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
