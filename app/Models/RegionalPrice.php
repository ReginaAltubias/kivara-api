<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RegionalPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'currency_code',
        'price_monthly',
        'price_yearly',
        'extra_child_price',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'extra_child_price' => 'decimal:2',
    ];
}
