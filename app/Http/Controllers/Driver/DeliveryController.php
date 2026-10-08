<?php

namespace App\Http\Controllers\Driver;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    public function index()
    {
        $base = Auth::user()->deliveries()->with('order')->latest();

        $incoming = (clone $base)->where('status', 'assigned')->get();
        $active = (clone $base)->where('status', 'out_for_delivery')->get();

        return view('driver.deliveries', [
            'incoming' => $incoming,
            'activeDeliveries' => $active,
            'perOrderEarnings' => (float) config('delivery.driver_earnings', 10.00),
            'currency' => config('delivery.currency', 'ILS'),
        ]);
    }

    public function history()
    {
        $history = Auth::user()->deliveries()
            ->where('status', 'delivered')
            ->with('order')
            ->latest()
            ->paginate(20);

        return view('driver.history', compact('history'));
    }

    public function show(Delivery $delivery)
    {
        $this->authorize('manage', $delivery);

        return view('driver.delivery', compact('delivery'));
    }

    public function pickup(Delivery $delivery)
    {
        $this->authorize('manage', $delivery);

        $order = $delivery->order;
        app(OrderService::class)->transition($order, OrderStatus::OutForDelivery->value);

        $delivery->update([
            'status' => 'out_for_delivery',
            'picked_up_at' => now(),
        ]);

        return back()->with('status', 'Order picked up. Out for delivery.');
    }

    public function deliver(Delivery $delivery)
    {
        $this->authorize('manage', $delivery);

        $order = $delivery->order;
        app(OrderService::class)->transition($order, OrderStatus::Delivered->value);

        $delivery->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return redirect()->route('driver.deliveries')->with('status', 'Order delivered.');
    }

    public function reject(Delivery $delivery)
    {
        $this->authorize('manage', $delivery);

        $order = $delivery->order;
        $delivery->delete();

        app(OrderService::class)->transition($order, OrderStatus::ReadyForPickup->value);

        return back()->with('status', 'Assignment rejected. Order returned to pool.');
    }
}
