<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeOrder(User $merchant, User $customer, string $status, float $total, ?Carbon $createdAt = null): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => $status,
            'items_total' => $total - 15,
            'delivery_fee' => 15.00,
            'total' => $total,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        if ($createdAt !== null) {
            Order::where('id', $order->id)->update(['created_at' => $createdAt]);
        }

        return $order->refresh();
    }

    public function test_dashboard_overview_shows_kpi_values(): void
    {
        $admin = $this->admin();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $customer = User::factory()->create();
        User::factory()->driver()->create(['status' => 'pending']); // 1 pending approval

        $this->makeOrder($merchant, $customer, 'delivered', 111.00);
        $this->makeOrder($merchant, $customer, 'pending', 55.00);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('id="today-revenue"', false);
        $response->assertSee('111.00', false); // today's revenue (US-70 rule: delivered sum(total))
        $response->assertSee('id="pending-approvals">1<', false);
        $response->assertSee('id="active-merchants">1<', false);
        $response->assertSee('delivered'); // orders-by-status table
        $response->assertSee('pending');
    }

    public function test_orders_filter_by_merchant_and_customer(): void
    {
        $admin = $this->admin();
        $m1 = User::factory()->merchant()->create(['status' => 'active', 'name' => 'Merchant One']);
        $m2 = User::factory()->merchant()->create(['status' => 'active', 'name' => 'Merchant Two']);
        $c1 = User::factory()->create();
        $c2 = User::factory()->create();

        $order1 = $this->makeOrder($m1, $c1, 'delivered', 40.00);
        $order2 = $this->makeOrder($m2, $c2, 'pending', 25.00);

        $response = $this->actingAs($admin)->get(route('admin.orders', [
            'merchant_id' => $m1->id,
            'customer_id' => $c1->id,
        ]));

        $response->assertOk();
        $response->assertSee('/admin/orders/'.$order1->id);
        $response->assertDontSee('/admin/orders/'.$order2->id);
    }

    public function test_users_filter_by_role_and_status(): void
    {
        $admin = $this->admin();
        $pendingDriver = User::factory()->driver()->create(['status' => 'pending', 'email' => 'pending.driver@example.com']);
        $activeCustomer = User::factory()->create(['email' => 'active.customer@example.com']);

        $response = $this->actingAs($admin)->get(route('admin.users', [
            'role' => 'driver',
            'status' => 'pending',
        ]));

        $response->assertOk();
        $response->assertSee('pending.driver@example.com');
        $response->assertDontSee('active.customer@example.com');
        $this->assertNotNull($pendingDriver->id);
        $this->assertNotNull($activeCustomer->id);
    }

    public function test_sales_report_respects_date_range(): void
    {
        $admin = $this->admin();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $customer = User::factory()->create();

        $this->makeOrder($merchant, $customer, 'delivered', 77.00, now()->subDays(3));
        $this->makeOrder($merchant, $customer, 'delivered', 133.00, now());

        // Full range: 77 + 133 = 210, count 2
        $range = $this->actingAs($admin)->get(route('admin.reports.sales', [
            'from' => now()->subDays(3)->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $range->assertOk();
        $range->assertSee('210.00', false);

        // Today only: 133, count 1 (old single-date rule still reachable via ?date=)
        $single = $this->actingAs($admin)->get(route('admin.reports.sales', [
            'date' => now()->toDateString(),
        ]));
        $single->assertOk();
        $single->assertSee('133.00', false);
        $single->assertDontSee('210.00', false);
    }

    public function test_non_admin_cannot_access_dashboard_or_users(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.users'))->assertForbidden();
    }
}
