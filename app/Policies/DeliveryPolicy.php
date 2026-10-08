<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function manage(User $user, Delivery $delivery): bool
    {
        return $user->id === $delivery->driver_id;
    }
}
