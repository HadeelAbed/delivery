<?php

namespace App\Notifications\Channels;

use App\Services\Push\WebPushService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    /**
     * Additive channel: database notifications are written by the
     * notification's own `database` channel independently; this channel
     * only attempts browser push and never throws.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $message = $notification->toWebPush($notifiable);

        if (! is_array($message)) {
            return;
        }

        $title = (string) ($message['title'] ?? 'Delivery Platform');
        $body = (string) ($message['body'] ?? '');

        if ($body === '') {
            return;
        }

        $data = $message['data'] ?? [];
        $data = is_array($data) ? $data : [];

        app(WebPushService::class)->sendToUser($notifiable, $title, $body, $data);
    }
}
