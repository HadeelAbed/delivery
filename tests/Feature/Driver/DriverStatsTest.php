<?php

namespace Tests\Feature\Driver;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Rating;
use App\Models\User;
use App\Services\DriverStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverStatsTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): User
    {
        return User::factory()->driver()->create(['status' => 'active']);
    }

    private function order(User $merchant, string $status = 'delivered'): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => $status,
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza'],
            'payment_method' => 'cod',
        ]);
    }

    public function test_stats_math_uses_fixed_config_earnings(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $driver = $this->driver();
        $svc = app(DriverStatsService::class);

        foreach ([5, 4] as $score) {
            $o = $this->order($merchant);
            Delivery::create(['order_id' => $o->id, 'driver_id' => $driver->id, 'status' => 'delivered', 'delivered_at' => now()]);
            Rating::create(['order_id' => $o->id, 'customer_id' => $o->customer_id, 'merchant_score' => 5, 'driver_score' => $score]);
        }
        // one active assigned must not count toward earnings
        $o2 = $this->order($merchant, 'assigned');
        Delivery::create(['order_id' => $o2->id, 'driver_id' => $driver->id, 'status' => 'assigned']);

        $stats = $svc->stats($driver);

        $this->assertSame(2, $stats['completed']);
        $this->assertSame(1, $stats['active']);
        $this->assertEquals(2 * config('delivery.driver_earnings'), $stats['earnings']);
        $this->assertEquals(4.5, $stats['rating']);
    }

    public function test_rating_null_when_no_ratings(): void
    {
        $driver = $this->driver();
        $this->assertNull(app(DriverStatsService::class)->stats($driver)['rating']);
    }

    public function test_dashboard_and_history_scoped_to_own_driver(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $a = $this->driver();
        $b = $this->driver();
        $o = $this->order($merchant);
        Delivery::create(['order_id' => $o->id, 'driver_id' => $a->id, 'status' => 'delivered', 'delivered_at' => now()]);

        $this->actingAs($a)->get(route('driver.dashboard'))->assertOk()->assertSee((string) config('delivery.driver_earnings'));
        $this->actingAs($a)->get(route('driver.deliveries.history'))->assertOk()->assertSee('order-'.$o->id);
        $this->actingAs($b)->get(route('driver.deliveries.history'))->assertOk()->assertDontSee('order-'.$o->id);
    }
}
