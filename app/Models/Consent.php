<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasUuids;

    protected $fillable = [
        'child_profile_id',
        'consent_type',
        'is_granted',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class, 'child_profile_id');
    }
}
