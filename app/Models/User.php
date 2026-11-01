<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    protected $fillable = [
        'tenant_id',
        'family_id',
        'partner_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'activation_code',
        'activation_expires_at',
        'role',
        'pairing_code',
        'is_active',
        'notification_settings',
        'phone',
        'avatar',
        'country',
        'gender',
        'language',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pairing_code',
        'activation_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activation_expires_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'is_active' => 'boolean',
        'notification_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relação: Pertence a Tenant (nullable)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Relação: Pertence a Family (nullable)
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * Relação: Muitos Perfis de Crianças (se role = parent)
     */
    public function childProfiles(): HasMany
    {
        return $this->hasMany(ChildProfile::class, 'parent_id');
    }

    /**
     * Relação: Muitas Carteiras (Polymorphic)
     */
    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'walletable');
    }

    /**
     * Carteira principal do utilizador (atalho para o Dashboard).
     */
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    /**
     * Relação: Muitos Pedidos de Level-Up (se role = parent)
     */
    public function levelUpRequests(): HasMany
    {
        return $this->hasMany(LevelUpRequest::class, 'parent_id');
    }

    /**
     * Relação: Muitas Configurações de Mesada (se role = parent)
     */
    public function allowanceConfigs(): HasMany
    {
        return $this->hasMany(AllowanceConfig::class, 'parent_id');
    }

    /**
     * Relação: Turmas onde o utilizador é professor
     */
    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_user')
                    ->using(ClassroomUser::class)
                    ->withPivot('role', 'child_profile_id', 'joined_at', 'left_at')
                    ->wherePivot('role', 'teacher')
                    ->withTimestamps();
    }

    /**
     * Relação: Turmas criadas pelo utilizador
     */
    public function createdClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /**
     * Relação: Avaliações/correções realizadas pelo professor
     */
    public function reviewedProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'reviewed_by');
    }

    public function onboardingProgress()
    {
        return $this->hasOne(OnboardingProgress::class);
    }
}
