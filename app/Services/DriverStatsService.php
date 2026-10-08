<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Rating;
use App\Models\User;

class DriverStatsService
{
    public function perOrderEarnings(): float
    {
        return (float) config('delivery.driver_earnings', 10.00);
    }

    public function stats(User $driver): array
    {
        $completed = Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->count();

        $active = Delivery::where('driver_id', $driver->id)
            ->whereIn('status', ['assigned', 'out_for_delivery'])
            ->count();

        $rating = Rating::whereIn('order_id', function ($q) use ($driver) {
            $q->select('order_id')->from('deliveries')
                ->where('driver_id', $driver->id)
                ->where('status', 'delivered');
        })->whereNotNull('driver_score')->avg('driver_score');

        return [
            'completed' => $completed,
            'active' => $active,
            'earnings' => round($completed * $this->perOrderEarnings(), 2),
            'rating' => $rating === null ? null : round((float) $rating, 2),
            'per_order' => $this->perOrderEarnings(),
            'currency' => config('delivery.currency', 'ILS'),
        ];
    }
}
