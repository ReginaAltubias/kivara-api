<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'reward_amount',
        'child_id',
        'due_date',
        'is_recurring',
        'recurrence_pattern',
        'status',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'is_recurring' => 'boolean',
        'reward_amount' => 'decimal:2',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_id');
    }
}
