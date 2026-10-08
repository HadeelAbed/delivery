<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'driver_id' => User::factory(),
            'status' => 'assigned',
            'picked_up_at' => null,
            'delivered_at' => null,
        ];
    }
}
