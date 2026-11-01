<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Premio entregue por um parceiro a uma turma/escola vencedora de um desafio. */
class ChallengePrize extends Model
{
    use HasUuids;

    protected $fillable = [
        'partner_id', 'partner_challenge_id', 'classroom_id', 'tenant_id', 'title', 'category', 'value_aoa',
        'status', 'delivery_date', 'recipient_representative', 'delivery_address', 'tracking_code', 'proof_document_url',
    ];

    protected $casts = ['value_aoa' => 'decimal:2', 'delivery_date' => 'date'];

    public function challenge()
    {
        return $this->belongsTo(PartnerChallenge::class, 'partner_challenge_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /** Formato partilhado pelas galerias do parceiro e da escola. */
    public function present(): array
    {
        $this->loadMissing(['challenge.program', 'classroom.tenant', 'partner']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'challenge_name' => $this->challenge?->title,
            'program_name' => $this->challenge?->program?->title,
            'school_name' => $this->classroom?->tenant?->name,
            'classroom_name' => $this->classroom?->name,
            'province' => $this->classroom?->tenant?->province ?? '',
            'sponsor' => $this->partner?->name,
            'delivery_date' => $this->delivery_date?->toDateString(),
            'status' => $this->status,
            'value_aoa' => (float) $this->value_aoa,
            'value_kvc' => (float) $this->value_aoa * 10,
            'recipient_representative' => $this->recipient_representative,
            'delivery_address' => $this->delivery_address,
            'tracking_code' => $this->tracking_code,
            'proof_document_url' => $this->proof_document_url,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
