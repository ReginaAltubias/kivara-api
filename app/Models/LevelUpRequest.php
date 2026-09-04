<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelUpRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'child_profile_id',
        'parent_id',
        'from_phase',
        'to_phase',
        'current_xp',
        'required_xp',
        'reason',
        'status',
        'approval_notes',
        'rejection_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_profile_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
