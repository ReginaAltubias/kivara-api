<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $activationUrl;

    public function __construct(public User $user, public string $activationCode)
    {
        $this->activationUrl = rtrim(env('FRONTEND_URL', 'http://localhost:8080'), '/')
            . '/verify-email?email=' . urlencode($user->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'KIVARA - O seu código de ativação');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth.otp');
    }
}
