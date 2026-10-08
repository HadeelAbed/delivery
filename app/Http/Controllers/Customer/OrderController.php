<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Auth::user()->customerOrders()->latest()->paginate(20);

        return view('customer.orders', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return view('customer.order', compact('order'));
    }
}
