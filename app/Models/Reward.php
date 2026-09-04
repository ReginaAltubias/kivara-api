<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'cost',
        'child_id',
        'icon',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_id');
    }
}
