<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OutForDelivery extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'out_for_delivery',
            'order_id' => $this->order->id,
            'message' => 'Order #'.$this->order->id.' is out for delivery.',
        ];
    }
}
