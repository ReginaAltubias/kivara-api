<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'tenant_type',
        'currency',
        'is_active',
        'email',
        'admin_email',
        'domain',
        'description',
        'subscription_tier_id',
        'subscription_period',
        'subscription_ends_at',
        'extra_children',
        'nif',
        'school_type',
        'education_level',
        'country',
        'province',
        'city',
        'address',
        'phone',
        'website',
        'director_name',
        'director_phone',
        'director_email',
        'logo_url',
        'agreement',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'agreement' => 'array',
        'subscription_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relação: Muitos Utilizadores
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relação: Muitas Famílias
     */
    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    /**
     * Relação: Muitas Turmas
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * Relação: Muitas Candidaturas de Escolas
     */
    public function schoolApplications(): HasMany
    {
        return $this->hasMany(SchoolApplication::class);
    }

    /**
     * Relação: Muitas Carteiras
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Relação: Todas as Subscrições da Escola
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Relação: Subscrição Atual / Mais Recente
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Relação: Faturas da Escola
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Verifica se a escola possui uma subscrição válida
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }
}
