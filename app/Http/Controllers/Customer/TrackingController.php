<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        abort_unless($order->status === OrderStatus::OutForDelivery, 404);

        $location = app(TrackingService::class)->latestForOrder($order);

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'driver_location' => $location ? [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
                'fresh' => app(TrackingService::class)->isLocationFresh($location),
            ] : null,
        ]);
    }
}
