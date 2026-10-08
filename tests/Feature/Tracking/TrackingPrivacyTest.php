<?php

namespace Tests\Feature\Tracking;

use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOutForDeliveryOrder(): array
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'out_for_delivery',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'out_for_delivery',
        ]);

        DriverLocation::create([
            'driver_id' => $driver->id,
            'order_id' => $order->id,
            'latitude' => 31.51,
            'longitude' => 34.48,
        ]);

        return [$order, $customer, $merchant, $driver];
    }

    public function test_customer_sees_fresh_driver_location(): void
    {
        [$order, $customer] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(31.51, $data['driver_location']['latitude']);
        $this->assertTrue($data['driver_location']['fresh']);
    }

    public function test_completed_order_hides_location(): void
    {
        [$order, $customer] = $this->makeOutForDeliveryOrder();
        $order->update(['status' => 'delivered']);

        $response = $this->actingAs($customer)->get(route('customer.orders.tracking', $order));

        $response->assertStatus(404);
    }

    public function test_third_party_cannot_view_tracking(): void
    {
        [$order, , $merchant] = $this->makeOutForDeliveryOrder();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)->get(route('customer.orders.tracking', $order));

        $response->assertStatus(403);
    }

    public function test_merchant_cannot_view_customer_tracking(): void
    {
        [$order, , $merchant] = $this->makeOutForDeliveryOrder();

        $response = $this->actingAs($merchant)->get(route('customer.orders.tracking', $order));

        $response->assertStatus(403);
    }
}
