<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $loginUrl;

    public function __construct(public Partner $partner, public bool $approved)
    {
        $this->loginUrl = rtrim(env('FRONTEND_URL', 'http://localhost:8080'), '/') . '/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->approved
                ? 'KIVARA - A sua candidatura de parceiro foi aprovada'
                : 'KIVARA - Candidatura de parceiro'
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partners.decision');
    }
}
