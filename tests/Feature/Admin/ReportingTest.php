<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_totals_delivered_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);

        Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'delivered',
            'items_total' => 10.00,
            'delivery_fee' => 15.00,
            'total' => 25.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
            'created_at' => today(),
        ]);

        Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'delivered',
            'items_total' => 20.00,
            'delivery_fee' => 15.00,
            'total' => 35.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
            'created_at' => today(),
        ]);

        Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 50.00,
            'delivery_fee' => 15.00,
            'total' => 65.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
            'created_at' => today(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.sales', ['date' => today()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee('60');
        $response->assertSee('2');
    }
}
