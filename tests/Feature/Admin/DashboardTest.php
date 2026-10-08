<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);

        $deliveredOrder = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'delivered',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 10.00,
            'delivery_fee' => 15.00,
            'total' => 25.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders', ['status' => 'delivered']));

        $response->assertStatus(200);
        $response->assertSee('delivered');
        // Only the delivered order row is listed (spec US-70: only Delivered orders returned).
        // Row-scoped assertions: the filter UI legitimately lists every status label.
        $response->assertSee('/admin/orders/'.$deliveredOrder->id);
        $response->assertDontSee('/admin/orders/'.$pendingOrder->id);
    }

    public function test_admin_approval_is_audit_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = User::factory()->merchant()->create();

        $this->actingAs($admin)->post(route('admin.users.approve', $merchant));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approve_merchant',
            'actor_id' => $admin->id,
        ]);
    }
}
