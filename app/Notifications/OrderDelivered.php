<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderDelivered extends Notification
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
            'type' => 'order_delivered',
            'order_id' => $this->order->id,
            'message' => 'Order #'.$this->order->id.' has been delivered.',
        ];
    }

    /**
     * @return array{title: string, body: string, data: array{order_id: int}}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Order delivered',
            'body' => 'Order #'.$this->order->id.' has been delivered.',
            'data' => ['order_id' => $this->order->id],
        ];
    }
}
