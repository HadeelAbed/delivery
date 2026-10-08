<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\User;
use App\Notifications\NewOrder;
use App\Notifications\OrderAccepted;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderDelivered;
use App\Notifications\OrderFailed;
use App\Notifications\OrderReadyForPickup;
use App\Notifications\OutForDelivery;

class SendOrderNotifications
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        switch ($event->to) {
            case 'pending':
                User::find($order->merchant_id)?->notify(new NewOrder($order));
                break;

            case 'merchant_accepted':
                User::find($order->customer_id)?->notify(new OrderAccepted($order));
                break;

            case 'ready_for_pickup':
                $driverId = $order->delivery?->driver_id;
                if ($driverId) {
                    User::find($driverId)?->notify(new OrderReadyForPickup($order));
                }
                break;

            case 'out_for_delivery':
                User::find($order->customer_id)?->notify(new OutForDelivery($order));
                break;

            case 'delivered':
                User::find($order->customer_id)?->notify(new OrderDelivered($order));
                User::find($order->merchant_id)?->notify(new OrderDelivered($order));
                break;

            case 'failed':
                User::find($order->customer_id)?->notify(new OrderFailed($order));
                User::find($order->merchant_id)?->notify(new OrderFailed($order));
                break;

            case 'cancelled':
                User::find($order->customer_id)?->notify(new OrderCancelled($order));
                User::find($order->merchant_id)?->notify(new OrderCancelled($order));
                break;
        }
    }
}
