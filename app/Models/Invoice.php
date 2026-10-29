<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'plan_id',
        'amount',
        'status',
        'payment_method',
        'payment_reference',
        'payment_payload',
        'paid_at',
        'type',
        'currency',
        'plan_name',
        'description',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'payment_payload' => 'array',
        'paid_at'         => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Relação: Pertence a uma Escola (Tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relação: Subscrição associada
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Relação: Plano faturado
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
