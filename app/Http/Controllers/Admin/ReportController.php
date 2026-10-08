<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        Gate::authorize('admin.access');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Single-date (original US-70 behavior) via ?date= ; range via ?from=&to=
        $from = $validated['from'] ?? $validated['date'] ?? today()->toDateString();
        $to = $validated['to'] ?? $from;

        $orders = Order::where('status', OrderStatus::Delivered->value)
            ->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->get();

        $total = $orders->sum('total');
        $count = $orders->count();

        return view('admin.reports', compact('orders', 'total', 'count', 'from', 'to'));
    }
}
