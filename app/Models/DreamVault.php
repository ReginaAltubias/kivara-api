<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DreamVault extends Model
{
    use HasUuids;

    protected $fillable = [
        'child_profile_id',
        'name',
        'target_amount',
        'current_amount',
        'icon',
        'description',
        'interest_rate',
        'is_active',
        'target_date',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'target_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_profile_id');
    }

    public function getProgressPercent(): float
    {
        if ($this->target_amount == 0) {
            return 0;
        }
        return ($this->current_amount / $this->target_amount) * 100;
    }
}
