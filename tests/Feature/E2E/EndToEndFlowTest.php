<?php

namespace Tests\Feature\E2E;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use App\Notifications\NewOrder;
use App\Notifications\OrderAccepted;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderDelivered;
use App\Notifications\OutForDelivery;
use App\Services\DriverStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedMerchantWithProduct(string $name = 'E2E Cafe'): array
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

        return [$merchant, $profile, $product];
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

    private function placeOrderViaHttp(User $customer, Product $product, string $method = 'cod'): Order
    {
        $this->actingAs($customer)->post(route('customer.cart.add'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertSessionHas('status');

        $this->actingAs($customer)->post(route('customer.checkout.place'), [
            'address' => ['label' => 'Gaza, Al-Rimal, Bldg 5', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => $method,
        ])->assertSessionHas('status', 'Order placed successfully.');

        return Order::where('customer_id', $customer->id)->firstOrFail();
    }

    public function test_full_cod_journey_from_cart_to_admin_report(): void
    {
        [$merchant, , $product] = $this->seedMerchantWithProduct();
        $driver = $this->seedOnlineDriver();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // 1. Customer: cart -> checkout (COD)
        $order = $this->placeOrderViaHttp($customer, $product);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame(25.00, (float) $order->items_total);
        $this->assertSame(40.00, (float) $order->total);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'cod', 'status' => 'pending_cod']);
        $this->assertDatabaseHas('notifications', ['type' => NewOrder::class, 'notifiable_id' => $merchant->id]);

        // 2. Merchant: accept with prep time -> mark ready (fires AssignOrderJob inline via sync queue)
        $this->actingAs($merchant)->post(route('merchant.orders.accept', $order), ['prep_time_minutes' => 15])
            ->assertSessionHas('status', 'Order accepted.');
        $this->assertSame('merchant_accepted', $order->fresh()->status->value);
        $this->assertDatabaseHas('notifications', ['type' => OrderAccepted::class, 'notifiable_id' => $customer->id]);

        $this->actingAs($merchant)->post(route('merchant.orders.ready', $order))
            ->assertSessionHas('status', 'Order marked ready for pickup.');
        $order->refresh();
        $this->assertSame('assigned', $order->status->value);
        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->assertSame($driver->id, $delivery->driver_id);

        // 3. Driver: pickup -> deliver
        $this->actingAs($driver)->post(route('driver.deliveries.pickup', $delivery))
            ->assertSessionHas('status', 'Order picked up. Out for delivery.');
        $this->assertSame('out_for_delivery', $order->fresh()->status->value);
        $this->assertDatabaseHas('notifications', ['type' => OutForDelivery::class, 'notifiable_id' => $customer->id]);

        $this->actingAs($driver)->post(route('driver.deliveries.deliver', $delivery))
            ->assertRedirect(route('driver.deliveries'));
        $order->refresh();
        $delivery->refresh();
        $this->assertSame('delivered', $order->status->value);
        $this->assertSame('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertDatabaseHas('notifications', ['type' => OrderDelivered::class, 'notifiable_id' => $customer->id]);
        $this->assertDatabaseHas('notifications', ['type' => OrderDelivered::class, 'notifiable_id' => $merchant->id]);

        // 4. Customer: rate merchant 5 / driver 4 (REQ-19 hop)
        $this->actingAs($customer)->post(route('customer.orders.rate.submit', $order), [
            'order_id' => $order->id,
            'merchant_score' => 5,
            'driver_score' => 4,
            'comment' => 'Great service',
        ]);
        $this->assertDatabaseHas('ratings', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'merchant_score' => 5,
            'driver_score' => 4,
        ]);

        // 5. Driver stats derived from the journey (Sprint 3 service, config earnings rule)
        $stats = app(DriverStatsService::class)->stats($driver);
        $this->assertSame(1, $stats['completed']);
        $this->assertEquals(config('delivery.driver_earnings'), $stats['earnings']);
        $this->assertEquals(4.0, $stats['rating']);

        // 6. Admin: sales report today includes the delivered order (US-70 rule)
        $report = $this->actingAs($admin)->get(route('admin.reports.sales'));
        $report->assertOk();
        $report->assertSee('40.00');
        $report->assertSee('/admin/orders/'.$order->id);
    }

    public function test_cancellation_journey_ends_cleanly(): void
    {
        [$merchant, , $product] = $this->seedMerchantWithProduct('Cancel Cafe');
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $order = $this->placeOrderViaHttp($customer, $product);

        $this->actingAs($merchant)->post(route('merchant.orders.reject', $order), ['reason' => 'Out of stock'])
            ->assertSessionHas('status', 'Order rejected.');

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('Out of stock', $order->reject_reason);
        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
        $this->assertDatabaseHas('notifications', ['type' => OrderCancelled::class, 'notifiable_id' => $customer->id]);
        $this->assertDatabaseHas('notifications', ['type' => OrderCancelled::class, 'notifiable_id' => $merchant->id]);

        // Cancelled order must not appear in the admin sales report
        $report = $this->actingAs($admin)->get(route('admin.reports.sales'));
        $report->assertOk();
        $report->assertDontSee('/admin/orders/'.$order->id);
    }

    public function test_rating_hop_is_reachable_and_owner_scoped(): void
    {
        [$merchant] = $this->seedMerchantWithProduct('Rate Cafe');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $order = Order::create([
            'customer_id' => $owner->id,
            'merchant_id' => $merchant->id,
            'status' => 'delivered',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        // Owner can open the rating form (was 403 before R1 repair)
        $this->actingAs($owner)->get(route('customer.orders.rate', $order))->assertOk();

        // Owner submits a rating (was 500 before R2 repair)
        $this->actingAs($owner)->post(route('customer.orders.rate.submit', $order), [
            'order_id' => $order->id,
            'merchant_score' => 4,
            'driver_score' => 5,
        ]);
        $this->assertSame(1, Rating::where('order_id', $order->id)->count());

        // Second rating for the same order is rejected (one rating per order)
        $this->actingAs($owner)->post(route('customer.orders.rate.submit', $order), [
            'order_id' => $order->id,
            'merchant_score' => 1,
            'driver_score' => 1,
        ]);
        $this->assertSame(1, Rating::where('order_id', $order->id)->count());

        // Non-owner is forbidden (policy scoping)
        $this->actingAs($stranger)->get(route('customer.orders.rate', $order))->assertForbidden();
    }
}
