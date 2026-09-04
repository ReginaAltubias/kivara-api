<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountActivationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $temporaryPassword;
    public string $activationCode;
    public string $activationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $temporaryPassword, string $activationCode)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->activationCode = $activationCode;
        $this->activationUrl = "https://app.kivara.co.ao/verify-email?email=" . urlencode($user->email);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bem-vindo(a) ao KIVARA - Credenciais de Acesso e Ativação de Conta",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.activation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
