<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrder extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_id' => $this->order->id,
            'message' => 'New order #'.$this->order->id.' received.',
        ];
    }

    /**
     * @return array{title: string, body: string, data: array{order_id: int}}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'New order received',
            'body' => 'New order #'.$this->order->id.' received.',
            'data' => ['order_id' => $this->order->id],
        ];
    }
}
