<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentClassroomInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $classroomName;
    public string $teacherName;
    public string $schoolName;
    public string $inviteCode;

    /**
     * Create a new message instance.
     */
    public function __construct(string $classroomName, string $teacherName, string $schoolName, string $inviteCode)
    {
        $this->classroomName = $classroomName;
        $this->teacherName = $teacherName;
        $this->schoolName = $schoolName;
        $this->inviteCode = $inviteCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Convite para a turma {$this->classroomName} no KIVARA",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.students.classroom-invitation',
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
