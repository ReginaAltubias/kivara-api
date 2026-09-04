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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
