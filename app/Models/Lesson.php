<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\LessonContentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'trail_id',
        'title',
        'content_type',
        'content_body',
        'media_url',
        'xp_reward',
        'status',
        'order',
    ];

    protected $casts = [
        'content_type' => LessonContentType::class,
        'xp_reward' => 'integer',
        'status' => ContentStatus::class,
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Trilho a que a lição pertence.
     */
    public function trail(): BelongsTo
    {
        return $this->belongsTo(Trail::class);
    }

    /**
     * Modelos de missão associados a esta lição.
     */
    public function missionTemplates(): HasMany
    {
        return $this->hasMany(MissionTemplate::class);
    }
}
