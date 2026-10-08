<?php

namespace Tests\Feature\E2E;

use App\Models\Category;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\SyncOutbox;
use App\Models\User;
use App\Notifications\OrderDelivered;
use App\Notifications\OutForDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function seedMerchantWithProduct(string $name = 'Sync Cafe'): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => $name,
            'address' => 'Gaza, Al-Rimal',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Margherita', 'price' => 12.50]);

        return [$merchant, $product];
    }

    private function seedOnlineDriver(): User
    {
        $driver = User::factory()->driver()->create(['status' => 'active', 'is_online' => true]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 31.51,
            'longitude' => 34.48,
        ]);

        return $driver;
    }

    /**
     * Web journey up to assignment: cart -> checkout -> accept -> ready (AssignOrderJob runs inline).
     *
     * @return array{0: Order, 1: User, 2: User, 3: User} [order, driver, merchant, customer]
     */
    private function journeyToAssigned(): array
    {
        [$merchant, $product] = $this->seedMerchantWithProduct();
        $driver = $this->seedOnlineDriver();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('customer.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->actingAs($customer)->post(route('customer.checkout.place'), [
            'address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);
        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($merchant)->post(route('merchant.orders.accept', $order), ['prep_time_minutes' => 10]);
        $this->actingAs($merchant)->post(route('merchant.orders.ready', $order));

        $order->refresh();
        $this->assertSame('assigned', $order->status->value);

        return [$order, $driver, $merchant, $customer];
    }

    private function bearer(User $driver): string
    {
        return $driver->createToken('offline-journey')->plainTextToken;
    }

    public function test_offline_journey_queued_batch_syncs_with_bearer_token(): void
    {
        [$order, $driver, $merchant, $customer] = $this->journeyToAssigned();
        $admin = User::factory()->admin()->create();

        // Driver goes offline (web route, session auth)
        $this->actingAs($driver)->post(route('driver.toggle-online'))
            ->assertSessionHas('status', 'You are now offline.');
        $this->assertFalse((bool) $driver->fresh()->is_online);

        // While offline the client queues two actions with idempotency keys + timestamps (REQ-11)
        $batch = [
            ['client_uuid' => 'j-1', 'type' => 'out_for_delivery', 'payload' => ['order_id' => $order->id], 'at' => now()->subMinutes(2)->toIso8601String()],
            ['client_uuid' => 'j-2', 'type' => 'delivered', 'payload' => ['order_id' => $order->id], 'at' => now()->subMinute()->toIso8601String()],
        ];

        // Reconnect: sync the batch via bearer token (auth:sanctum, not session)
        $response = $this->withHeader('Authorization', 'Bearer '.$this->bearer($driver))
            ->postJson('/api/sync', ['actions' => $batch]);

        $response->assertOk();
        $this->assertSame('processed', $response->json('ack.0.status'));
        $this->assertSame('processed', $response->json('ack.1.status'));
        $this->assertSame('delivered', $order->fresh()->status->value);

        $this->assertDatabaseHas('sync_outbox', ['id' => 'j-1', 'user_id' => $driver->id, 'status' => 'completed']);
        $this->assertDatabaseHas('sync_outbox', ['id' => 'j-2', 'user_id' => $driver->id, 'status' => 'completed']);

        // Replay of the identical batch must be idempotent — no duplicates (REQ-11)
        $replay = $this->withHeader('Authorization', 'Bearer '.$this->bearer($driver))
            ->postJson('/api/sync', ['actions' => $batch]);
        $replay->assertOk();
        $this->assertSame('already_processed', $replay->json('ack.0.status'));
        $this->assertSame('already_processed', $replay->json('ack.1.status'));
        $this->assertSame(1, SyncOutbox::where('id', 'j-1')->count());
        $this->assertSame(1, SyncOutbox::where('id', 'j-2')->count());
        $this->assertSame('delivered', $order->fresh()->status->value);

        // Events fanned out through the API path too
        $this->assertDatabaseHas('notifications', ['type' => OutForDelivery::class, 'notifiable_id' => $customer->id]);
        $this->assertDatabaseHas('notifications', ['type' => OrderDelivered::class, 'notifiable_id' => $customer->id]);
        $this->assertDatabaseHas('notifications', ['type' => OrderDelivered::class, 'notifiable_id' => $merchant->id]);

        // Downstream convergence: admin sales report sees the synced delivery (US-70 rule)
        $report = $this->actingAs($admin)->get(route('admin.reports.sales'));
        $report->assertOk();
        $report->assertSee('/admin/orders/'.$order->id);
        $report->assertSee('40.00');
    }

    public function test_stale_offline_action_cannot_regress_delivered_order(): void
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = User::factory()->driver()->create(['status' => 'active']);

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

        // Multi-action batch drives ready -> assigned -> out_for_delivery -> delivered in ONE request.
        // Observed current behavior: this path creates no `deliveries` row (delivery-row creation
        // lives in AssignOrderJob's web flow; sync payload carries only order_id).
        $batch = [
            ['client_uuid' => 's-1', 'type' => 'assigned', 'payload' => ['order_id' => $order->id], 'at' => now()->subMinutes(3)->toIso8601String()],
            ['client_uuid' => 's-2', 'type' => 'out_for_delivery', 'payload' => ['order_id' => $order->id], 'at' => now()->subMinutes(2)->toIso8601String()],
            ['client_uuid' => 's-3', 'type' => 'delivered', 'payload' => ['order_id' => $order->id], 'at' => now()->subMinute()->toIso8601String()],
        ];
        $sync = $this->withHeader('Authorization', 'Bearer '.$this->bearer($driver))
            ->postJson('/api/sync', ['actions' => $batch]);
        $sync->assertOk();
        $this->assertSame('processed', $sync->json('ack.0.status'));
        $this->assertSame('processed', $sync->json('ack.2.status'));
        $this->assertSame('delivered', $order->fresh()->status->value);
        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);

        // A stale queued action (older client timestamp) must not regress server state (REQ-11)
        $stale = $this->withHeader('Authorization', 'Bearer '.$this->bearer($driver))
            ->postJson('/api/sync', ['actions' => [[
                'client_uuid' => 'u-stale',
                'type' => 'out_for_delivery',
                'payload' => ['order_id' => $order->id],
                'at' => now()->subMinutes(5)->toIso8601String(),
            ]]]);

        $stale->assertOk();
        $this->assertSame('invalid_transition', $stale->json('conflicts.0.reason'));
        $this->assertSame('delivered', $stale->json('conflicts.0.from'));
        $this->assertSame('delivered', $order->fresh()->status->value); // server state retained
        $this->assertSame(0, SyncOutbox::where('id', 'u-stale')->count()); // conflicted action not persisted
    }

    public function test_mixed_batch_reports_partial_conflict_and_keeps_server_state(): void
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = User::factory()->driver()->create(['status' => 'active']);

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

        $batch = [
            ['client_uuid' => 'm-1', 'type' => 'delivered', 'payload' => ['order_id' => $order->id], 'at' => now()->toIso8601String()],
            ['client_uuid' => 'm-2', 'type' => 'assigned', 'payload' => ['order_id' => $order->id], 'at' => now()->toIso8601String()],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->bearer($driver))
            ->postJson('/api/sync', ['actions' => $batch]);

        $response->assertOk();
        // One request reports both outcomes: conflict for the illegal hop, ack for the legal one
        $this->assertSame('invalid_transition', $response->json('conflicts.0.reason'));
        $this->assertSame('m-1', $response->json('conflicts.0.uuid'));
        $this->assertSame('processed', $response->json('ack.0.status'));
        $this->assertSame('m-2', $response->json('ack.0.uuid'));
        // Server state advanced only as far as the legal action; outbox has only m-2
        $this->assertSame('assigned', $order->fresh()->status->value);
        $this->assertSame(1, SyncOutbox::where('id', 'm-2')->count());
        $this->assertSame(0, SyncOutbox::where('id', 'm-1')->count());
    }
}
