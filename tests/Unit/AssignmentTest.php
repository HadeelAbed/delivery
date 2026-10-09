<?php

namespace Tests\Unit;

use App\Jobs\AssignOrderJob;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearest_driver_is_assigned(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'ready_for_pickup',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $nearDriver = User::factory()->driver()->create(['status' => 'active', 'is_online' => true]);
        $farDriver = User::factory()->driver()->create(['status' => 'active', 'is_online' => true]);

        DriverLocation::create([
            'driver_id' => $nearDriver->id,
            'latitude' => 31.501,
            'longitude' => 34.471,
        ]);

        DriverLocation::create([
            'driver_id' => $farDriver->id,
            'latitude' => 32.0,
            'longitude' => 35.0,
        ]);

        AssignOrderJob::dispatchSync($order);

        $order->refresh();
        $this->assertSame('assigned', $order->status->value);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'driver_id' => $nearDriver->id,
        ]);
    }

    public function test_no_driver_available_leaves_order_ready(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'ready_for_pickup',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        AssignOrderJob::dispatchSync($order);

        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
    }

    public function test_repeated_execution_does_not_create_duplicate_delivery(): void
    {
        [$order] = $this->readyOrderWithOnlineDriver();

        // First run assigns the nearest driver.
        AssignOrderJob::dispatchSync($order);

        $this->assertSame('assigned', $order->fresh()->status->value);
        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());

        // A retry/replay of the same job must not create a second delivery.
        AssignOrderJob::dispatchSync($order->fresh());

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
        $this->assertSame('assigned', $order->fresh()->status->value);
    }

    public function test_existing_delivery_prevents_second_assignment(): void
    {
        [$order, $driver] = $this->readyOrderWithOnlineDriver();

        // Simulate a concurrent run that already created a delivery but has not
        // yet flipped the order status (the mid-race window). The idempotency
        // guard must prevent a second delivery regardless of order status.
        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        AssignOrderJob::dispatchSync($order);

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
    }

    /**
     * @return array{0: Order, 1: User}
     */
    private function readyOrderWithOnlineDriver(): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'ready_for_pickup',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $driver = User::factory()->driver()->create(['status' => 'active', 'is_online' => true]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 31.501,
            'longitude' => 34.471,
        ]);

        return [$order, $driver];
    }
}
