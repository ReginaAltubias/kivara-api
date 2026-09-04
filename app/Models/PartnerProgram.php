<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PartnerProgram extends Model
{
    use HasUuids;

    protected $fillable = [
        'partner_id',
        'title',
        'description',
        'allocated_budget',
        'status',
    ];

    protected $casts = [
        'allocated_budget' => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /**
     * Relação: Pertence a um Parceiro Institucional
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Relação: Desafios lançados sob a alçada deste Programa
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(PartnerChallenge::class, 'program_id');
    }

    /**
     * Total de orçamento já comprometido em desafios
     */
    public function committedBudget(): int
    {
        return (int) $this->challenges()->sum('reward_pool');
    }

    /**
     * Saldo de orçamento restante disponível para novos desafios
     */
    public function remainingBudget(): int
    {
        $remaining = $this->allocated_budget - $this->committedBudget();
        return max(0, $remaining);
    }

    /**
     * Verifica se o programa tem orçamento suficiente para um montante
     */
    public function hasAvailableBudget(int $amount): bool
    {
        return $this->remainingBudget() >= $amount;
    }

    /**
     * Scope: Programas ativos
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
