<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_url',
        'tier',
        'required_xp',
        'trigger_type',
        'trigger_data',
        'is_active',
    ];

    protected $casts = [
        'trigger_data' => 'json',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(ChildProfile::class, 'user_badges')
            ->withPivot(['unlocked_at'])
            ->withTimestamps();
    }
}
