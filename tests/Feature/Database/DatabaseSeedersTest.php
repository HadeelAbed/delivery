<?php

namespace Tests\Feature\Database;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DriverProfile;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_deterministic_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'demo.customer@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertSame(UserStatus::Active, $customer->status);

        $merchantUser = User::where('email', 'demo.merchant@example.com')->first();
        $this->assertNotNull($merchantUser);
        $this->assertSame(UserStatus::Active, $merchantUser->status);

        $merchant = Merchant::where('user_id', $merchantUser->id)->first();
        $this->assertNotNull($merchant);
        $this->assertSame('Demo Restaurant', $merchant->business_name);
        $this->assertSame(2, $merchant->categories()->count());
        $this->assertSame(3, $merchant->products()->count());

        $driver = User::where('email', 'demo.driver@example.com')->first();
        $this->assertNotNull($driver);
        $this->assertTrue((bool) $driver->is_online);
        $this->assertNotNull($driver->driverProfile);

        $this->assertSame(1, Order::where('status', OrderStatus::Pending->value)->count());
        $this->assertSame(1, Order::where('status', OrderStatus::Assigned->value)->count());
        $this->assertSame(1, Order::where('status', OrderStatus::Delivered->value)->count());

        foreach (Order::all() as $order) {
            $this->assertNotNull($order->payment);
            $this->assertSame('cod', $order->payment->method);
        }

        $assigned = Order::where('status', OrderStatus::Assigned->value)->first();
        $this->assertSame('assigned', $assigned->delivery->status);
        $this->assertSame($driver->id, $assigned->delivery->driver_id);

        $delivered = Order::where('status', OrderStatus::Delivered->value)->first();
        $this->assertSame('delivered', $delivered->delivery->status);
        $this->assertNotNull($delivered->delivery->picked_up_at);
        $this->assertNotNull($delivered->delivery->delivered_at);

        // Existing seeders preserved.
        $this->assertSame(1, User::where('role', UserRole::Admin->value)->count());
        $this->assertNotNull(User::where('email', 'test@example.com')->first());
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::count();
        $orders = Order::count();
        $products = Product::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($users, User::count());
        $this->assertSame($orders, Order::count());
        $this->assertSame($products, Product::count());
    }

    public function test_demo_seeder_is_skipped_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        (new DemoSeeder)->run();

        $this->assertNull(User::where('email', 'demo.customer@example.com')->first());
        $this->assertNull(User::where('email', 'demo.merchant@example.com')->first());
        $this->assertNull(User::where('email', 'demo.driver@example.com')->first());
    }

    public function test_domain_factories_create_valid_models(): void
    {
        $merchant = Merchant::factory()->create();
        $this->assertNotNull($merchant->user);

        $category = Category::factory()->create(['merchant_id' => $merchant->id]);
        $this->assertSame($merchant->id, $category->merchant_id);

        $product = Product::factory()->create();
        $this->assertNotNull($product->category);

        $order = Order::factory()->create();
        $this->assertNotNull($order->customer);
        $this->assertNotNull($order->merchant);
        $this->assertGreaterThanOrEqual($order->items_total, $order->total);

        $item = OrderItem::factory()->create();
        $this->assertNotNull($item->order);
        $this->assertNotNull($item->product);

        $delivery = Delivery::factory()->create();
        $this->assertSame('assigned', $delivery->status);

        $profile = DriverProfile::factory()->create();
        $this->assertNotNull($profile->user);
    }
}
