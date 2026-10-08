<?php

namespace Tests\Feature\Merchant;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedMerchant(): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza, Al-Rimal',
        ]);

        return [$merchant, $profile];
    }

    private function makePendingOrder(User $merchant): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);
    }

    public function test_merchant_accepts_order_with_prep_time(): void
    {
        [$merchant] = $this->makeApprovedMerchant();
        $order = $this->makePendingOrder($merchant);

        $this->actingAs($merchant)->post(route('merchant.orders.accept', $order), [
            'prep_time_minutes' => 20,
        ])->assertSessionHas('status', 'Order accepted.');

        $order->refresh();
        $this->assertSame('merchant_accepted', $order->status->value);
        $this->assertSame(20, $order->prep_time_minutes);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order_merchant_accepted',
            'entity_id' => $order->id,
        ]);
    }

    public function test_customer_is_notified_when_order_accepted(): void
    {
        [$merchant] = $this->makeApprovedMerchant();
        $order = $this->makePendingOrder($merchant);
        $customer = User::find($order->customer_id);

        $this->actingAs($merchant)->post(route('merchant.orders.accept', $order), [
            'prep_time_minutes' => 20,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_merchant_marks_order_ready_for_pickup(): void
    {
        [$merchant] = $this->makeApprovedMerchant();
        $order = $this->makePendingOrder($merchant);

        $this->actingAs($merchant)->post(route('merchant.orders.accept', $order), [
            'prep_time_minutes' => 20,
        ]);

        $this->actingAs($merchant)->post(route('merchant.orders.ready', $order))
            ->assertSessionHas('status', 'Order marked ready for pickup.');

        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
    }

    public function test_merchant_cannot_accept_another_merchants_order(): void
    {
        [$merchantA] = $this->makeApprovedMerchant();
        [, $profileB] = $this->makeApprovedMerchant();

        $merchantB = User::where('id', '!=', $merchantA->id)->merchant()->firstOrFail();
        $order = $this->makePendingOrder($merchantB);

        $response = $this->actingAs($merchantA)->post(route('merchant.orders.accept', $order), [
            'prep_time_minutes' => 20,
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        [$merchant] = $this->makeApprovedMerchant();
        $order = $this->makePendingOrder($merchant);

        $this->actingAs($merchant)->post(route('merchant.orders.ready', $order));

        $this->assertSame('pending', $order->fresh()->status->value);
    }
}
