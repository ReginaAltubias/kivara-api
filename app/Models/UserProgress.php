<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class UserProgress extends Model
{
    use HasUuids;

    protected $fillable = [
        // Identificadores do aluno (um ou outro)
        'child_profile_id',
        'user_id',              // Teen (tabela users)

        // Objeto de aprendizagem
        'learning_object_id',

        // Estado geral
        'completed',
        'attempts',
        'best_score',
        'total_xp_earned',
        'total_coins_earned',
        'last_attempt_data',
        'completed_at',

        // Fluxo de avaliação pedagógica B2B
        'status',
        'submission_url',
        'submission_note',
        'submission_payload',
        'submitted_at',
        'reviewed_by',
        'feedback',
        'teacher_feedback',
        'reviewed_at',
        'evaluated_at',
        'xp_awarded',
        'coins_awarded',
    ];

    protected $casts = [
        'completed'          => 'boolean',
        'last_attempt_data'  => 'json',
        'submission_payload' => 'json',
        'completed_at'       => 'datetime',
        'submitted_at'       => 'datetime',
        'reviewed_at'        => 'datetime',
        'evaluated_at'       => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // =========================================================================
    // Relações
    // =========================================================================

    /** Criança (fase 1 - child_profiles) */
    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_profile_id');
    }

    /** Adolescente (fase 2/3 - users) */
    public function teen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Objeto de aprendizagem associado */
    public function learningObject(): BelongsTo
    {
        return $this->belongsTo(LearningObject::class);
    }

    /** Professor que avaliou */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /** Fila de avaliação: submissões à espera de correção */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    /** Apenas submissões aprovadas */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
