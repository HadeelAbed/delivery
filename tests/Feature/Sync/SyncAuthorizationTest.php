<?php

namespace Tests\Feature\Sync;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\SyncOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security regression coverage for offline-sync authorization (Policy B).
 *
 * Offline sync is a driver-only surface: delivery-lifecycle transitions require a
 * Delivery record assigned to the authenticated driver. Assignment (`assigned`)
 * stays server-side (AssignOrderJob). Customers, merchants, unassigned orders,
 * other drivers' deliveries, and client-driven assignment are rejected as
 * `unauthorized` without mutating order or delivery state.
 */
class SyncAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'assigned', ?User $customer = null, ?User $merchant = null): Order
    {
        $customer ??= User::factory()->create();
        $merchant ??= User::factory()->merchant()->create(['status' => 'active']);

        return Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => $status,
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);
    }

    private function action(string $uuid, string $type, $orderId): array
    {
        return [
            'client_uuid' => $uuid,
            'type' => $type,
            'payload' => ['order_id' => $orderId],
            'at' => now()->toIso8601String(),
        ];
    }

    public function test_assigned_driver_can_sync_delivery_transition(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);
        $order = $this->makeOrder('assigned');
        Delivery::create(['order_id' => $order->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [$this->action('a-1', 'out_for_delivery', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('processed', $response->json('ack.0.status'));
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        $this->assertSame(1, SyncOutbox::where('id', 'a-1')->count());
    }

    public function test_different_driver_cannot_sync_another_drivers_order(): void
    {
        $owner = User::factory()->driver()->create(['status' => 'active']);
        $intruder = User::factory()->driver()->create(['status' => 'active']);
        $order = $this->makeOrder('assigned');
        Delivery::create(['order_id' => $order->id, 'driver_id' => $owner->id, 'status' => 'assigned']);

        $response = $this->actingAs($intruder)->postJson('/api/sync', [
            'actions' => [$this->action('x-1', 'out_for_delivery', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));
        $this->assertEmpty($response->json('ack'));
        $this->assertSame('assigned', $order->fresh()->status->value);
        $this->assertSame(0, SyncOutbox::where('id', 'x-1')->count());
    }

    public function test_customer_cannot_perform_driver_delivery_transition(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);
        $customer = User::factory()->create();
        $order = $this->makeOrder('assigned', $customer);
        Delivery::create(['order_id' => $order->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $response = $this->actingAs($customer)->postJson('/api/sync', [
            'actions' => [$this->action('c-1', 'out_for_delivery', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));
        $this->assertSame('assigned', $order->fresh()->status->value);
    }

    public function test_merchant_cannot_perform_driver_delivery_transition(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $order = $this->makeOrder('assigned', null, $merchant);
        Delivery::create(['order_id' => $order->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $response = $this->actingAs($merchant)->postJson('/api/sync', [
            'actions' => [$this->action('mm-1', 'out_for_delivery', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));
        $this->assertSame('assigned', $order->fresh()->status->value);
    }

    public function test_unassigned_order_cannot_be_modified_through_driver_sync(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);
        $order = $this->makeOrder('assigned');
        // No Delivery row: nothing is assigned to this driver.

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [$this->action('u-1', 'out_for_delivery', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));
        $this->assertSame('assigned', $order->fresh()->status->value);
        $this->assertSame(0, SyncOutbox::where('id', 'u-1')->count());
    }

    public function test_client_cannot_assign_order_through_sync(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);
        $order = $this->makeOrder('ready_for_pickup');
        Delivery::create(['order_id' => $order->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [$this->action('as-1', 'assigned', $order->id)],
        ]);

        $response->assertOk();
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));
        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
        $this->assertSame(0, SyncOutbox::where('id', 'as-1')->count());
    }

    public function test_mixed_authorized_and_unauthorized_batch_cannot_bypass_authorization(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $authorized = $this->makeOrder('assigned');
        Delivery::create(['order_id' => $authorized->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $foreignOwner = User::factory()->driver()->create(['status' => 'active']);
        $foreign = $this->makeOrder('assigned');
        Delivery::create(['order_id' => $foreign->id, 'driver_id' => $foreignOwner->id, 'status' => 'assigned']);

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [
                $this->action('ok-1', 'out_for_delivery', $authorized->id),
                $this->action('no-1', 'out_for_delivery', $foreign->id),
            ],
        ]);

        $response->assertOk();
        $this->assertSame('processed', $response->json('ack.0.status'));
        $this->assertSame('unauthorized', $response->json('conflicts.0.reason'));

        // Authorized order advanced; foreign order untouched.
        $this->assertSame('out_for_delivery', $authorized->fresh()->status->value);
        $this->assertSame('assigned', $foreign->fresh()->status->value);
        $this->assertSame(1, SyncOutbox::where('id', 'ok-1')->count());
        $this->assertSame(0, SyncOutbox::where('id', 'no-1')->count());
    }

    public function test_missing_order_id_is_reported_safely(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [$this->action('mo-1', 'out_for_delivery', null)],
        ]);

        $response->assertOk();
        $this->assertSame('order_not_found', $response->json('conflicts.0.reason'));
        $this->assertEmpty($response->json('ack'));
    }

    public function test_malformed_order_id_is_reported_safely(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $response = $this->actingAs($driver)->postJson('/api/sync', [
            'actions' => [$this->action('mf-1', 'delivered', 'not-a-number')],
        ]);

        $response->assertOk();
        $this->assertSame('order_not_found', $response->json('conflicts.0.reason'));
        $this->assertEmpty($response->json('ack'));
        $this->assertSame(0, SyncOutbox::where('id', 'mf-1')->count());
    }
}
