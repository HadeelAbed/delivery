<?php

namespace Database\Seeders;

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
use App\Services\Payment\PaymentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Deterministic demo data for local/testing environments only.
     *
     * - Fixed identities, emails and prices (same data every run).
     * - Idempotent: re-running is a no-op once demo data exists.
     * - Production-safe: refuses to run outside local/testing.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder skipped: demo data only seeds in local/testing.');

            return;
        }

        if (User::where('email', 'demo.customer@example.com')->exists()) {
            $this->command?->info('Demo data already seeded.');

            return;
        }

        $customer = User::create([
            'name' => 'Demo Customer',
            'email' => 'demo.customer@example.com',
            'password' => 'password',
            'phone' => '0500000001',
            'role' => UserRole::Customer->value,
            'status' => UserStatus::Active->value,
        ]);

        $merchantUser = User::create([
            'name' => 'Demo Merchant',
            'email' => 'demo.merchant@example.com',
            'password' => 'password',
            'phone' => '0500000002',
            'role' => UserRole::Merchant->value,
            'status' => UserStatus::Active->value,
        ]);

        $merchant = Merchant::create([
            'user_id' => $merchantUser->id,
            'business_name' => 'Demo Restaurant',
            'address' => 'Gaza',
            'latitude' => 31.50,
            'longitude' => 34.47,
        ]);

        $driver = User::create([
            'name' => 'Demo Driver',
            'email' => 'demo.driver@example.com',
            'password' => 'password',
            'phone' => '0500000003',
            'role' => UserRole::Driver->value,
            'status' => UserStatus::Active->value,
            'is_online' => true,
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'vehicle_type' => 'motorcycle',
            'service_area' => ['Gaza'],
        ]);

        $mains = Category::create(['merchant_id' => $merchant->id, 'name' => 'Main Course']);
        $drinks = Category::create(['merchant_id' => $merchant->id, 'name' => 'Drinks']);

        $shawarma = Product::create([
            'category_id' => $mains->id,
            'name' => 'Demo Shawarma',
            'description' => 'Chicken shawarma wrap',
            'price' => 15.00,
            'is_available' => true,
        ]);
        $burger = Product::create([
            'category_id' => $mains->id,
            'name' => 'Demo Burger',
            'description' => 'Classic cheeseburger',
            'price' => 20.00,
            'is_available' => true,
        ]);
        $cola = Product::create([
            'category_id' => $drinks->id,
            'name' => 'Demo Cola',
            'description' => 'Soft drink 330ml',
            'price' => 5.00,
            'is_available' => true,
        ]);

        $fee = (float) config('delivery.delivery_fee');
        $address = ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47];

        // 1) Pending order — waiting in the merchant queue.
        $pending = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchantUser->id,
            'status' => OrderStatus::Pending->value,
            'items_total' => 30.00,
            'delivery_fee' => $fee,
            'total' => 30.00 + $fee,
            'delivery_address' => $address,
            'payment_method' => 'cod',
        ]);
        OrderItem::create(['order_id' => $pending->id, 'product_id' => $shawarma->id, 'quantity' => 2, 'unit_price' => 15.00]);
        app(PaymentService::class)->process($pending);

        // 2) Assigned order — active delivery on the driver dashboard.
        $assigned = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchantUser->id,
            'status' => OrderStatus::Assigned->value,
            'items_total' => 25.00,
            'delivery_fee' => $fee,
            'total' => 25.00 + $fee,
            'delivery_address' => $address,
            'payment_method' => 'cod',
        ]);
        OrderItem::create(['order_id' => $assigned->id, 'product_id' => $burger->id, 'quantity' => 1, 'unit_price' => 20.00]);
        OrderItem::create(['order_id' => $assigned->id, 'product_id' => $cola->id, 'quantity' => 1, 'unit_price' => 5.00]);
        app(PaymentService::class)->process($assigned);
        Delivery::create(['order_id' => $assigned->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        // 3) Delivered order (created today) — feeds the admin dashboard KPIs
        //    and the US-70 sales report (delivered orders, sum(total), today).
        $delivered = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchantUser->id,
            'status' => OrderStatus::Delivered->value,
            'items_total' => 45.00,
            'delivery_fee' => $fee,
            'total' => 45.00 + $fee,
            'delivery_address' => $address,
            'payment_method' => 'cod',
        ]);
        OrderItem::create(['order_id' => $delivered->id, 'product_id' => $shawarma->id, 'quantity' => 3, 'unit_price' => 15.00]);
        app(PaymentService::class)->process($delivered);
        Delivery::create([
            'order_id' => $delivered->id,
            'driver_id' => $driver->id,
            'status' => 'delivered',
            'picked_up_at' => now()->subHour(),
            'delivered_at' => now()->subMinutes(30),
        ]);
    }
}
