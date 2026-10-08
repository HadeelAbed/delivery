<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $itemsTotal = fake()->randomFloat(2, 10, 100);
        $deliveryFee = (float) config('delivery.delivery_fee');

        return [
            'customer_id' => User::factory(),
            'merchant_id' => User::factory(),
            'status' => OrderStatus::Pending->value,
            'items_total' => $itemsTotal,
            'delivery_fee' => $deliveryFee,
            'total' => round($itemsTotal + $deliveryFee, 2),
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ];
    }
}
