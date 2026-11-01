<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mission extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'reward_type',
        'reward_amount',
        'child_id',
        'deadline',
        'status',
        'type',
        'target_amount',
        'kiva_points_reward',
        'difficulty',
        'mission_template_id',
        'lesson_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reward_amount' => 'decimal:2',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_id');
    }
}
