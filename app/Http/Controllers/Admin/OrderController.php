<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.access');

        $filters = $request->only(['status', 'merchant_id', 'customer_id']);

        $query = Order::query()->with(['customer', 'merchant']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        $merchants = User::merchant()->orderBy('name')->get();
        $customers = User::where('role', 'customer')->orderBy('name')->get();

        return view('admin.orders', compact('orders', 'filters', 'merchants', 'customers'));
    }

    public function show(Order $order)
    {
        Gate::authorize('admin.access');

        return view('admin.order', compact('order'));
    }
}
