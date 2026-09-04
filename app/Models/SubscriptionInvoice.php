<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'family_id',
        'amount',
        'status',
        'pdf_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
    }
}
