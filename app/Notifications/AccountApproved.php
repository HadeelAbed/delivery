<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountApproved extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'account_approved',
            'message' => 'Your account has been approved. You can now access the platform.',
        ];
    }

    /**
     * @return array{title: string, body: string, data: array{}}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Account approved',
            'body' => 'Your account has been approved. You can now access the platform.',
            'data' => [],
        ];
    }
}
