<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\MapPoint;
use App\Contracts\MapProvider;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Live tracking page (polls `show` as JSON).
     *
     * Same authorization as `show`: owner-only and out_for_delivery only.
     */
    public function page(Order $order)
    {
        $this->authorize('view', $order);

        abort_unless($order->status === OrderStatus::OutForDelivery, 404);

        return view('customer.tracking', [
            'order' => $order,
            'map' => $this->mapContext($order),
        ]);
    }

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
            'route' => $this->routeEstimate($order, $location),
        ]);
    }

    /**
     * Public-safe pins for the map partial: the browser key (restricted by
     * referrer) and this order's own merchant/destination coordinates.
     * The server key is never part of this payload.
     *
     * @return array{browser_key: ?string, merchant: ?array{lat: float, lng: float, label: string}, destination: ?array{lat: float, lng: float, label: string}}
     */
    private function mapContext(Order $order): array
    {
        return [
            'browser_key' => config('maps.browser_key') ?: null,
            'merchant' => $this->merchantPoint($order),
            'destination' => $this->destinationPoint($order),
        ];
    }

    private function routeEstimate(Order $order, ?DriverLocation $location): ?array
    {
        if (! $location) {
            return null;
        }

        $destination = $this->destinationPoint($order);

        if (! $destination) {
            return null;
        }

        $estimate = app(MapProvider::class)->route(
            new MapPoint((float) $location->latitude, (float) $location->longitude),
            new MapPoint($destination['lat'], $destination['lng'])
        );

        return $estimate?->toArray();
    }

    private function merchantPoint(Order $order): ?array
    {
        $merchant = $order->merchant?->merchantProfile;

        if (! $merchant || $merchant->latitude === null || $merchant->longitude === null) {
            return null;
        }

        return [
            'lat' => (float) $merchant->latitude,
            'lng' => (float) $merchant->longitude,
            'label' => (string) $merchant->business_name,
        ];
    }

    private function destinationPoint(Order $order): ?array
    {
        $address = $order->delivery_address;

        if (! is_array($address)) {
            return null;
        }

        $lat = $address['lat'] ?? null;
        $lng = $address['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'label' => (string) ($address['label'] ?? 'Destination'),
        ];
    }
}
