<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_token(): void
    {
        $response = $this->getJson('/api/v1/orders');
        $response->assertStatus(401);
    }

    public function test_token_scopes_results_to_owner(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);

        $orderA = Order::create([
            'customer_id' => $customerA->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        Order::create([
            'customer_id' => $customerB->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 10.00,
            'delivery_fee' => 15.00,
            'total' => 25.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $token = $customerA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $orderA->id]);
    }

    public function test_shared_service_layer_is_used(): void
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create(['user_id' => $merchant->id, 'business_name' => 'Cafe', 'address' => 'Gaza']);
        $category = Category::create(['merchant_id' => $merchant->merchantProfile->id, 'name' => 'Pizza']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Margherita', 'price' => 12.50]);

        $order = app(OrderService::class)->place(
            $customer,
            $merchant,
            [['product_id' => $product->id, 'quantity' => 1]],
            ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'cod',
        );

        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $order->id]);
    }
}
