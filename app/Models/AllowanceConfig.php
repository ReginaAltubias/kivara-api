<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllowanceConfig extends Model
{
    use HasUuids;

    protected $table = 'allowance_configs';

    protected $fillable = [
        'child_profile_id',
        'parent_id',
        'base_amount',
        'frequency',
        'next_payment_date',
        'is_active',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'next_payment_date' => 'date',
        'is_active' => 'boolean',
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
}
