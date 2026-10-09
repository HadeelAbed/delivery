<?php

namespace Tests\Feature\Sync;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\SyncOutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriverWithOrder(string $orderStatus = 'ready_for_pickup'): array
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => $orderStatus,
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        return [$driver, $order];
    }

    public function test_queued_action_syncs_without_duplicate(): void
    {
        [$driver, $order] = $this->makeDriverWithOrder('assigned');

        // Legitimate offline-driver workflow: a delivery assigned to this driver.
        Delivery::create(['order_id' => $order->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $action = [
            'client_uuid' => 'u-1',
            'type' => 'out_for_delivery',
            'payload' => ['order_id' => $order->id],
            'at' => now()->toIso8601String(),
        ];

        $response = $this->actingAs($driver)->postJson('/api/sync', ['actions' => [$action]]);
        $response->assertStatus(200);
        $this->assertSame('processed', $response->json('ack.0.status'));

        $response2 = $this->actingAs($driver)->postJson('/api/sync', ['actions' => [$action]]);
        $response2->assertStatus(200);
        $this->assertSame('already_processed', $response2->json('ack.0.status'));

        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        $this->assertSame(1, SyncOutbox::where('id', 'u-1')->count());
    }

    public function test_out_of_order_action_is_rejected(): void
    {
        [$driver, $order] = $this->makeDriverWithOrder('ready_for_pickup');

        $action = [
            'client_uuid' => 'u-2',
            'type' => 'delivered',
            'payload' => ['order_id' => $order->id],
            'at' => now()->toIso8601String(),
        ];

        $response = $this->actingAs($driver)->postJson('/api/sync', ['actions' => [$action]]);
        $response->assertStatus(200);

        $this->assertNotEmpty($response->json('conflicts'));
        $this->assertSame('invalid_transition', $response->json('conflicts.0.reason'));
        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
    }

    public function test_stale_action_loses_to_newer_server_state(): void
    {
        [$driver, $order] = $this->makeDriverWithOrder('delivered');

        $action = [
            'client_uuid' => 'u-3',
            'type' => 'out_for_delivery',
            'payload' => ['order_id' => $order->id],
            'at' => now()->subMinutes(5)->toIso8601String(),
        ];

        $response = $this->actingAs($driver)->postJson('/api/sync', ['actions' => [$action]]);
        $response->assertStatus(200);

        $this->assertNotEmpty($response->json('conflicts'));
        $this->assertSame('delivered', $order->fresh()->status->value);
    }

    public function test_sync_requires_authentication(): void
    {
        $response = $this->postJson('/api/sync', ['actions' => []]);
        $response->assertStatus(401);
    }
}
