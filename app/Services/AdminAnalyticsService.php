<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\User;

class AdminAnalyticsService
{
    /**
     * KPI overview figures for the admin dashboard.
     * All rules reuse existing definitions: sales = delivered orders'
     * sum(total) for the day (SPEC-001 US-70 sales report rule).
     *
     * @return array<string, int|float|string>
     */
    public function overview(): array
    {
        $ordersByStatus = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $todayDelivered = Order::where('status', OrderStatus::Delivered->value)
            ->whereDate('created_at', today())
            ->get(['total']);

        return [
            'today' => today()->toDateString(),
            'today_delivered' => $todayDelivered->count(),
            'today_revenue' => round((float) $todayDelivered->sum('total'), 2),
            'total_orders' => (int) $ordersByStatus->sum(),
            'orders_by_status' => $ordersByStatus,
            'pending_approvals' => User::whereIn('role', [
                UserRole::Merchant->value,
                UserRole::Driver->value,
            ])->where('status', UserStatus::Pending->value)->count(),
            'active_merchants' => User::where('role', UserRole::Merchant->value)
                ->where('status', UserStatus::Active->value)->count(),
            'active_drivers' => User::where('role', UserRole::Driver->value)
                ->where('status', UserStatus::Active->value)->count(),
            'active_customers' => User::where('role', UserRole::Customer->value)
                ->where('status', UserStatus::Active->value)->count(),
            'currency' => config('delivery.currency', 'ILS'),
        ];
    }
}
