<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolChallengeVictoryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $schoolName;
    public $challengeName;
    public $rewardDescription;

    /**
     * Create a new message instance.
     */
    public function __construct($schoolName, $challengeName, $rewardDescription)
    {
        $this->schoolName = $schoolName;
        $this->challengeName = $challengeName;
        $this->rewardDescription = $rewardDescription;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Parabéns! A sua escola venceu o Desafio {$this->challengeName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.schools.challenge-victory',
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
