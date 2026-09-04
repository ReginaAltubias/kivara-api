<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningObject extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'title',
        'type',
        'topic',
        'level',
        'description',
        'content_data',
        'reward_xp',
        'reward_coins',
        'minimum_score_percent',
        'estimated_duration_minutes',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'content_data' => 'json',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function userProgress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(ChildProfile::class, 'user_progress')
            ->withPivot(['completed', 'attempts', 'best_score', 'total_xp_earned', 'total_coins_earned', 'last_attempt_data', 'completed_at']);
    }
}
