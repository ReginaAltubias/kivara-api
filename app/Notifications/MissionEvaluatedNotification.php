<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MissionEvaluatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $status, // 'approved' | 'rejected'
        public readonly string $missionTitle,
        public readonly int $xp = 0,
        public readonly int $coins = 0,
        public readonly ?string $feedback = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isApproved = $this->status === 'approved';

        $title = $isApproved
            ? "Missão Aprovada! 🎉"
            : "Missão Precisa de Revisão 📝";

        $message = $isApproved
            ? "A tua missão \"{$this->missionTitle}\" foi aprovada! Ganhaste +{$this->coins} Moedas e +{$this->xp} XP!"
            : "A tua missão \"{$this->missionTitle}\" foi devolvida pelo professor: " . ($this->feedback ?: 'Verifica os comentários e tenta novamente.');

        return [
            'type'          => 'mission_evaluated',
            'status'        => $this->status,
            'title'         => $title,
            'message'       => $message,
            'mission_title' => $this->missionTitle,
            'xp_awarded'    => $this->xp,
            'coins_awarded' => $this->coins,
            'feedback'      => $this->feedback,
        ];
    }

    /**
     * Payload Expo Push.
     *
     * @return array<string, mixed>
     */
    public function toExpoPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => $data,
        ];
    }
}
