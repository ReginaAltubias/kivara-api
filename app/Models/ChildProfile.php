<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class ChildProfile extends Model
{
    use HasUuids, Notifiable;

    protected $fillable = [
        'parent_id',
        'family_id',
        'tenant_id',
        'name',
        'username',
        'pin',
        'kivara_phase_id',
        'total_xp',
        'nickname',
        'date_of_birth',
        'avatar',
        'monthly_budget',
        'daily_spend_limit',
        'auth_method',
        'product_phase',
        'dashboard_role',
        'email',
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'date_of_birth' => 'date',
        'monthly_budget' => 'decimal:2',
        'daily_spend_limit' => 'decimal:2',
        'password' => 'hashed',
    ];

    /**
     * Relação: Pertence a Pai
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Relação: Pertence a Família
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Relação: Pertence a Tenant (Escola)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relação: Carteiras Polimórficas (Muitas carteiras: KVC Virtual, AOA Real)
     */
    public function wallets(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Wallet::class, 'walletable');
    }

    /**
     * Relação: Carteira Principal (Polymorphic MorphOne)
     */
    public function wallet(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    /**
     * Relação: Muitas Tarefas Domésticas
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'child_id');
    }

    /**
     * Relação: Muitas Recompensas Personalizadas
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'child_id');
    }

    /**
     * Relação: Muitas Turmas (Many-to-Many)
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_user')
            ->using(ClassroomUser::class)
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Relação: Muitos Pedidos de Level-Up
     */
    public function levelUpRequests(): HasMany
    {
        return $this->hasMany(LevelUpRequest::class);
    }

    /**
     * Relação: Uma Streak
     */
    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    /**
     * Relação: Uma Configuração de Mesada
     */
    public function allowanceConfig(): HasOne
    {
        return $this->hasOne(AllowanceConfig::class, 'child_profile_id');
    }

    /**
     * Relação: Muitos Cofres de Poupança
     */
    public function dreamVaults(): HasMany
    {
        return $this->hasMany(DreamVault::class, 'child_profile_id');
    }

    /**
     * Relação: Muitos Progressos de Aprendizagem
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Relação: Muitos Badges (Many-to-Many)
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot(['unlocked_at'])
            ->withTimestamps();
    }

    /**
     * Calcula a idade atual da criança
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Saldo Virtual (KVC)
     */
    public function getVirtualBalanceAttribute(): float
    {
        $virtualWallet = $this->wallets->firstWhere('currency', 'KVC') ?? $this->wallet;
        return $virtualWallet ? (float) $virtualWallet->balance : 0.0;
    }

    /**
     * Saldo Real (AOA)
     */
    public function getRealBalanceAttribute(): float
    {
        $realWallet = $this->wallets->firstWhere('currency', 'AOA');
        return $realWallet ? (float) $realWallet->balance : 0.0;
    }
}
