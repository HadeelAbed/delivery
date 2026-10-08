<?php

namespace Tests\Feature\Notification;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFanoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedMerchant(): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
        ]);
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        return [$merchant, $product];
    }

    public function test_new_order_notifies_merchant_only(): void
    {
        $customer = User::factory()->create();
        [$merchant, $product] = $this->makeApprovedMerchant();

        $order = app(OrderService::class)->place(
            $customer,
            $merchant,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'cod',
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $merchant->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $customer->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_accepted_order_notifies_customer(): void
    {
        $customer = User::factory()->create();
        [$merchant, $product] = $this->makeApprovedMerchant();

        $order = app(OrderService::class)->place(
            $customer,
            $merchant,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'cod',
        );

        app(OrderService::class)->transition($order, 'merchant_accepted', null, 20);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_cancelled_order_notifies_both_customer_and_merchant(): void
    {
        $customer = User::factory()->create();
        [$merchant, $product] = $this->makeApprovedMerchant();

        $order = app(OrderService::class)->place(
            $customer,
            $merchant,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'cod',
        );

        app(OrderService::class)->transition($order, 'cancelled', 'Out of stock');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $merchant->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_no_cross_role_leakage(): void
    {
        $customer = User::factory()->create();
        [$merchant, $product] = $this->makeApprovedMerchant();
        $driver = User::factory()->driver()->create(['status' => 'active']);

        app(OrderService::class)->place(
            $customer,
            $merchant,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'cod',
        );

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $driver->id,
            'notifiable_type' => User::class,
        ]);
    }
}
