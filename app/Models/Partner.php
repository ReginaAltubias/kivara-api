<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'logo_path',
        'contact_email',
        'is_active',
        'status',
        'nif',
        'sector',
        'phone',
        'country',
        'city',
        'address',
        'director_name',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
        'province',
        'website',
        'director_role',
        'director_email',
        'director_phone',
        'subscription_tier_id',
        'subscription_period',
        'subscription_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(PartnerChallenge::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(PartnerProgram::class);
    }

    /**
     * Carteiras do parceiro (Orçamento KVC / AOA)
     */
    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'walletable');
    }

    /**
     * Carteira principal KVC do parceiro
     */
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    /** Plano subscrito ainda em vigor (ou null). */
    public function activePlan(): ?SubscriptionPlan
    {
        if (! $this->subscription_tier_id || ! $this->subscription_ends_at || ! \Carbon\Carbon::parse($this->subscription_ends_at)->isFuture()) {
            return null;
        }

        return SubscriptionPlan::find($this->subscription_tier_id);
    }

    /** Limites efetivos: os do plano ativo ou, sem plano, os da configuracao. */
    public function limits(): array
    {
        $plan = $this->activePlan();

        return [
            'plan' => $plan,
            'max_programs' => (int) ($plan?->max_programs ?? config('partners.max_programs')),
            'max_children' => (int) ($plan?->max_children ?? config('partners.max_children')),
        ];
    }
}
