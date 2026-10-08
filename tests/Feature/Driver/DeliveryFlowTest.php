<?php

namespace Tests\Feature\Driver;

use App\Jobs\AssignOrderJob;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedDriver(string $name = 'Driver Dan'): User
    {
        return User::factory()->driver()->create(['status' => 'active', 'name' => $name]);
    }

    private function makeReadyOrder(User $merchant): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'ready_for_pickup',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);
    }

    public function test_online_driver_receives_assignment(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $driver = $this->makeApprovedDriver();
        $driver->update(['is_online' => true]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 31.51,
            'longitude' => 34.48,
        ]);

        $order = $this->makeReadyOrder($merchant);

        AssignOrderJob::dispatchSync($order);

        $order->refresh();
        $this->assertSame('assigned', $order->status->value);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);
    }

    public function test_offline_driver_does_not_receive_assignment(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $driver = $this->makeApprovedDriver();
        $driver->update(['is_online' => false]);

        $order = $this->makeReadyOrder($merchant);

        AssignOrderJob::dispatchSync($order);

        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
    }

    public function test_driver_pickup_transitions_to_out_for_delivery(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = $this->makeApprovedDriver();
        $order = $this->makeReadyOrder($merchant);
        $order->update(['status' => 'assigned']);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($driver)->post(route('driver.deliveries.pickup', $delivery))
            ->assertSessionHas('status', 'Order picked up. Out for delivery.');

        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->picked_up_at);
    }

    public function test_driver_delivery_completes_order(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = $this->makeApprovedDriver();
        $order = $this->makeReadyOrder($merchant);
        $order->update(['status' => 'out_for_delivery']);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'out_for_delivery',
            'picked_up_at' => now(),
        ]);

        $this->actingAs($driver)->post(route('driver.deliveries.deliver', $delivery))
            ->assertRedirect(route('driver.deliveries'));

        $this->assertSame('delivered', $order->fresh()->status->value);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivered_at);
    }

    public function test_driver_reject_returns_order_to_pool(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = $this->makeApprovedDriver();
        $order = $this->makeReadyOrder($merchant);
        $order->update(['status' => 'assigned']);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($driver)->post(route('driver.deliveries.reject', $delivery))
            ->assertSessionHas('status', 'Assignment rejected. Order returned to pool.');

        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
    }

    public function test_driver_cannot_pickup_another_drivers_delivery(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driverA = $this->makeApprovedDriver('Driver A');
        $driverB = $this->makeApprovedDriver('Driver B');
        $order = $this->makeReadyOrder($merchant);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driverA->id,
            'status' => 'assigned',
        ]);

        $response = $this->actingAs($driverB)->post(route('driver.deliveries.pickup', $delivery));

        $response->assertStatus(403);
    }
}
