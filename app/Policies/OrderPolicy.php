<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function manage(User $user, Order $order): bool
    {
        return $user->id === $order->merchant_id;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id;
    }

    public function rate(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id;
    }
}
