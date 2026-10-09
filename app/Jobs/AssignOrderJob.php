<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AssignOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Re-fetch inside the transaction and lock the order row so two
            // concurrent (or repeated) executions of this job cannot both pass
            // the ready-for-pickup guard and create duplicate deliveries.
            $order = Order::lockForUpdate()->find($this->order->id);

            if (! $order || $order->status->value !== OrderStatus::ReadyForPickup->value) {
                return;
            }

            // Idempotency guard: if a delivery already exists for this order a
            // previous run already assigned it. Never create a second one.
            if (Delivery::where('order_id', $order->id)->exists()) {
                return;
            }

            $merchant = User::find($order->merchant_id);
            $merchantLat = $merchant?->merchantProfile?->latitude;
            $merchantLng = $merchant?->merchantProfile?->longitude;

            if ($merchantLat === null || $merchantLng === null) {
                return;
            }

            $drivers = User::driver()
                ->where('status', 'active')
                ->where('is_online', true)
                ->get()
                ->map(function (User $driver) use ($merchantLat, $merchantLng) {
                    $location = DriverLocation::where('driver_id', $driver->id)
                        ->latest('recorded_at')
                        ->first();

                    if (! $location) {
                        return null;
                    }

                    $distance = $this->haversine(
                        (float) $merchantLat,
                        (float) $merchantLng,
                        (float) $location->latitude,
                        (float) $location->longitude,
                    );

                    return ['driver' => $driver, 'distance' => $distance];
                })
                ->filter()
                ->sortBy('distance');

            $nearest = $drivers->first();

            if (! $nearest) {
                return;
            }

            $driver = $nearest['driver'];

            Delivery::create([
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'status' => 'assigned',
            ]);

            $order->status = OrderStatus::Assigned;
            $order->save();

            event(new OrderStatusChanged($order, OrderStatus::ReadyForPickup->value, OrderStatus::Assigned->value));
        });
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
