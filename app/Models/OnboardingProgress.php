<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingProgress extends Model
{
    use HasFactory;

    protected $table = 'onboarding_progresses';

    protected $fillable = [
        'user_id',
        'current_step',
        'step_index',
        'completed',
        'skipped',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'skipped' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
