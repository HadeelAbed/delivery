<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedMerchant(string $name = 'Test Cafe'): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => $name,
            'address' => 'Gaza, Al-Rimal',
        ]);

        return [$merchant, $profile];
    }

    public function test_customer_can_place_order_with_correct_totals(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        $response = $this->actingAs($customer)
            ->withSession(['cart' => [
                'merchant_id' => $merchant->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ]])
            ->post(route('customer.checkout.place'), [
                'address' => ['label' => 'Gaza, Al-Rimal, Bldg 5', 'lat' => 31.5, 'lng' => 34.47],
                'payment_method' => 'cod',
            ]);

        $response->assertSessionHas('status', 'Order placed successfully.');

        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('pending', $order->status->value);
        $this->assertSame(25.00, (float) $order->items_total);
        $this->assertSame(15.00, (float) $order->delivery_fee);
        $this->assertSame(40.00, (float) $order->total);
        $this->assertSame('cod', $order->payment_method);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 12.50,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending_cod',
        ]);
    }

    public function test_merchant_receives_notification_on_new_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        $this->actingAs($customer)
            ->withSession(['cart' => [
                'merchant_id' => $merchant->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]])
            ->post(route('customer.checkout.place'), [
                'address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
                'payment_method' => 'cod',
            ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $merchant->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_rejected_order_cannot_be_assigned(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 12.50,
            'delivery_fee' => 15.00,
            'total' => 27.50,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $this->actingAs($merchant)->post(route('merchant.orders.reject', $order), [
            'reason' => 'Out of stock',
        ]);

        $order->refresh();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('Out of stock', $order->reject_reason);

        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
    }

    public function test_cart_cannot_contain_items_from_two_merchants(): void
    {
        $customer = User::factory()->create();
        [$merchantA, $profileA] = $this->makeApprovedMerchant('Cafe A');
        [, $profileB] = $this->makeApprovedMerchant('Cafe B');

        $catA = Category::create(['merchant_id' => $profileA->id, 'name' => 'Pizza']);
        $catB = Category::create(['merchant_id' => $profileB->id, 'name' => 'Burger']);

        $productA = Product::create(['category_id' => $catA->id, 'name' => 'Margherita', 'price' => 12.50]);
        $productB = Product::create(['category_id' => $catB->id, 'name' => 'Cheeseburger', 'price' => 20.00]);

        $this->actingAs($customer)->post(route('customer.cart.add'), [
            'product_id' => $productA->id,
            'quantity' => 1,
        ])->assertSessionHas('status');

        $response = $this->actingAs($customer)->post(route('customer.cart.add'), [
            'product_id' => $productB->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHas('error');
    }
}
