<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartnerChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'partner_name',
        'partner_id',
        'program_id',
        'title',
        'description',
        'goal_type',
        'goal_target',
        'reward_pool',
        'starts_at',
        'ends_at',
        'status',
        'winner_classroom_id',
        'school_reward_type',
        'school_reward_description',
        'school_reward_id',
        'tenant_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_challenge')
                    ->withPivot('current_progress', 'status')
                    ->withTimestamps();
    }

    public function winnerClassroom()
    {
        return $this->belongsTo(Classroom::class, 'winner_classroom_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function schoolReward()
    {
        return $this->belongsTo(SchoolReward::class, 'school_reward_id');
    }

    public function program()
    {
        return $this->belongsTo(PartnerProgram::class, 'program_id');
    }
}
