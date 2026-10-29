<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Support\Str;

class Classroom extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'year',
        'grade_level',
        'teacher_id',
        'is_active',
        'invite_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($classroom) {
            if (empty($classroom->invite_code)) {
                do {
                    $code = 'KVR-' . Str::upper(Str::random(5));
                } while (static::where('invite_code', $code)->exists());
                $classroom->invite_code = $code;
            }
        });
    }

    /**
     * Relação: Pertence a Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relação: Pertence a Professor (Criador original)
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Retorna apenas os Professores desta turma.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_user')
                    ->using(ClassroomUser::class)
                    ->withPivot('role', 'child_profile_id', 'joined_at', 'left_at')
                    ->wherePivot('role', 'teacher')
                    ->withTimestamps();
    }

    /**
     * Retorna apenas os Alunos desta turma.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(ChildProfile::class, 'classroom_user', 'classroom_id', 'child_profile_id')
                    ->using(ClassroomUser::class)
                    ->withPivot('role', 'user_id', 'joined_at', 'left_at')
                    ->wherePivot('role', 'student')
                    ->withTimestamps();
    }

    /** Alunos que sao perfis de crianca (alias de students()). */
    public function childStudents(): BelongsToMany
    {
        return $this->students();
    }

    /** Alunos adolescentes (contas em `users`), ligados por classroom_user.user_id. */
    public function teenStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_user', 'classroom_id', 'user_id')
                    ->using(ClassroomUser::class)
                    ->withPivot('role', 'child_profile_id', 'joined_at', 'left_at')
                    ->wherePivot('role', 'student')
                    ->withTimestamps();
    }

    /**
     * Retorna os desafios desta turma.
     */
    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(PartnerChallenge::class, 'classroom_challenge')
                    ->withPivot('current_progress', 'status')
                    ->withTimestamps();
    }
}
