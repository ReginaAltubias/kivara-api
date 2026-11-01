<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Mensagem enviada por um professor/coordenador a alunos ou encarregados. */
class TeacherMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $type = 'teacher_notification',
        public readonly ?string $senderName = null,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'type' => $this->type, 'sender' => $this->senderName];
    }

    /** @return array<string, mixed> */
    public function toExpoPush(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->message, 'data' => ['type' => $this->type]];
    }
}
