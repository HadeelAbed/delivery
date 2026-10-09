<?php

namespace Tests\Feature;

use App\Jobs\AssignOrderJob;
use App\Models\DriverLocation;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentAsyncQueueTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the same eligible-driver + located-merchant fixture used by the
     * unit-level AssignmentTest, but exercise the real `database` queue path
     * that production uses (phpunit.xml forces QUEUE_CONNECTION=sync).
     *
     * NOTE: `orders.merchant_id` references the merchant USER id (the job does
     * User::find($order->merchant_id)->merchantProfile), mirroring AssignmentTest.
     *
     * @return array{0: Order, 1: User}
     */
    private function seedReadyOrderWithOnlineDriver(): array
    {
        $merchantUser = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create([
            'user_id' => $merchantUser->id,
            'business_name' => 'Async Merchant',
            'address' => 'Gaza',
            'latitude' => 31.5,
            'longitude' => 34.47,
        ]);

        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchantUser->id,
            'status' => 'ready_for_pickup',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => 'cod',
        ]);

        $driver = User::factory()->driver()->create(['status' => 'active', 'is_online' => true]);
        DriverLocation::create([
            'driver_id' => $driver->id,
            'latitude' => 31.501,
            'longitude' => 34.471,
        ]);

        return [$order, $driver];
    }

    public function test_assign_job_is_queued_not_run_inline_under_database_queue(): void
    {
        config(['queue.default' => 'database']);

        [$order, $driver] = $this->seedReadyOrderWithOnlineDriver();

        // Real dispatch through the queued connection (NOT dispatchSync).
        AssignOrderJob::dispatch($order);

        // A queued job leaves a row in the `jobs` table; an inline (sync) run
        // would have executed immediately and left no row.
        $this->assertSame(1, DB::table('jobs')->count(), 'AssignOrderJob should be pushed to the database queue, not run inline.');

        // Because it is async, the order is NOT yet assigned at dispatch time.
        $this->assertDatabaseMissing('deliveries', ['order_id' => $order->id]);
        $this->assertSame('ready_for_pickup', $order->fresh()->status->value);
    }

    public function test_processing_the_queue_assigns_the_nearest_driver(): void
    {
        config(['queue.default' => 'database']);

        [$order, $driver] = $this->seedReadyOrderWithOnlineDriver();

        AssignOrderJob::dispatch($order);
        $this->assertSame(1, DB::table('jobs')->count());

        // Simulate the production worker draining exactly one queued job.
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        // The worker consumed the job and performed the assignment.
        $this->assertSame(0, DB::table('jobs')->count(), 'queue:work should drain the queued job.');
        $this->assertDatabaseHas('deliveries', ['order_id' => $order->id, 'driver_id' => $driver->id]);
        $this->assertSame('assigned', $order->fresh()->status->value);
    }

    public function test_no_job_is_queued_when_using_sync_connection(): void
    {
        // Documents the phpunit default: the job runs inline and never touches
        // the `jobs` table. This is why the async path needs explicit coverage.
        config(['queue.default' => 'sync']);

        [$order, $driver] = $this->seedReadyOrderWithOnlineDriver();

        AssignOrderJob::dispatch($order);

        $this->assertSame(0, DB::table('jobs')->count());
        // Ran inline: assignment already happened synchronously.
        $this->assertDatabaseHas('deliveries', ['order_id' => $order->id, 'driver_id' => $driver->id]);
        $this->assertSame('assigned', $order->fresh()->status->value);
    }
}
