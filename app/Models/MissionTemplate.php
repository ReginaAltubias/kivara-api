<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\MissionTaskType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MissionTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'lesson_id',
        'title',
        'description',
        'task_type',
        'validation_rules',
        'base_coin_reward',
        'base_xp_reward',
        'status',
    ];

    protected $casts = [
        'task_type' => MissionTaskType::class,
        'validation_rules' => 'array',
        'base_coin_reward' => 'integer',
        'base_xp_reward' => 'integer',
        'status' => ContentStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Lição associada (nullable para missões avulsas).
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
