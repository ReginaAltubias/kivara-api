<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'nif',
        'school_type',
        'education_level',
        'country',
        'province',
        'city',
        'address',
        'phone',
        'contact_email',
        'website',
        'director_name',
        'director_phone',
        'director_email',
        'motivation',
        'status',
        'tenant_id',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relação: Pertence a Tenant (quando aprovada)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se foi aprovada
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Verifica se foi rejeitada
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
