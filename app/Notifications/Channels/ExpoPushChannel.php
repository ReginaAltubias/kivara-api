<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    /**
     * Envia a notificação via Expo Push API.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpoPush')) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        if (! is_array($message)) {
            return;
        }

        $token = $notifiable->routeNotificationFor('expoPush', $notification)
            ?? data_get($notifiable, 'expo_push_token')
            ?? data_get($notifiable, 'device_token');

        if (! $token || ! is_string($token)) {
            return;
        }

        $payload = array_merge([
            'to' => $token,
            'sound' => 'default',
        ], $message);

        try {
            Http::acceptJson()
                ->asJson()
                ->post('https://exp.host/--/api/v2/push/send', $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Expo push failed', [
                'notifiable' => $notifiable->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
