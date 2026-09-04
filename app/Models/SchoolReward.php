<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SchoolReward extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'icon',
        'price_in_coins',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'price_in_coins' => 'integer',
        'stock'          => 'integer',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * Relação: Pertence a uma Escola (Tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relação: Desafios que utilizam esta recompensa simbólica
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(PartnerChallenge::class, 'school_reward_id');
    }

    /**
     * Scope: Apenas recompensas ativas
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Recompensas compráveis na loja da escola (preço > 0)
     */
    public function scopeStoreAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
                     ->where('price_in_coins', '>', 0)
                     ->where(function ($q) {
                         $q->whereNull('stock')->orWhere('stock', '>', 0);
                     });
    }
}
