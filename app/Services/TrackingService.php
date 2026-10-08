<?php

namespace App\Services;

use App\Models\DriverLocation;
use App\Models\Order;

class TrackingService
{
    public function recordLocation(int $driverId, ?int $orderId, float $lat, float $lng): DriverLocation
    {
        return DriverLocation::create([
            'driver_id' => $driverId,
            'order_id' => $orderId,
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => now(),
        ]);
    }

    public function latestForOrder(Order $order): ?DriverLocation
    {
        return DriverLocation::where('order_id', $order->id)
            ->latest('recorded_at')
            ->first();
    }

    public function isLocationFresh(?DriverLocation $location, int $maxAgeSeconds = 10): bool
    {
        if (! $location) {
            return false;
        }

        return $location->recorded_at->diffInSeconds(now()) <= $maxAgeSeconds;
    }
}
