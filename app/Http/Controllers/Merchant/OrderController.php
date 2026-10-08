<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOrderRequest;
use App\Http\Requests\RejectOrderRequest;
use App\Jobs\AssignOrderJob;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Auth::user()->merchantOrders()->latest()->paginate(20);

        return view('merchant.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->authorize('manage', $order);

        return view('merchant.order', compact('order'));
    }

    public function accept(Order $order, AcceptOrderRequest $request)
    {
        $this->authorize('manage', $order);

        app(OrderService::class)->accept($order, $request->validated()['prep_time_minutes']);

        return back()->with('status', 'Order accepted.');
    }

    public function reject(Order $order, RejectOrderRequest $request)
    {
        $this->authorize('manage', $order);

        app(OrderService::class)->reject($order, $request->validated()['reason']);

        return back()->with('status', 'Order rejected.');
    }

    public function markReady(Order $order)
    {
        $this->authorize('manage', $order);

        app(OrderService::class)->markReady($order);

        AssignOrderJob::dispatch($order);

        return back()->with('status', 'Order marked ready for pickup.');
    }
}
