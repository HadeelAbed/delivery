<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --card: #ffffff; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--fg); margin: 0; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .summary-card { background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .summary-card h3 { font-size: 14px; color: var(--muted); margin-bottom: 8px; }
        .summary-card .value { font-size: 24px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: var(--border); }
    </style>
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="content">
        <h1 class="page-title">Dashboard</h1>

        <div class="summary-grid">
            <div class="summary-card">
                <h3>Today's Revenue ({{ $stats['today'] }})</h3>
                <div class="value" id="today-revenue">{{ number_format($stats['today_revenue'], 2) }} {{ $stats['currency'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Delivered Today</h3>
                <div class="value" id="today-delivered">{{ $stats['today_delivered'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Total Orders</h3>
                <div class="value" id="total-orders">{{ $stats['total_orders'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Pending Approvals</h3>
                <div class="value" id="pending-approvals">{{ $stats['pending_approvals'] }}</div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h3>Active Merchants</h3>
                <div class="value" id="active-merchants">{{ $stats['active_merchants'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Active Drivers</h3>
                <div class="value" id="active-drivers">{{ $stats['active_drivers'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Active Customers</h3>
                <div class="value" id="active-customers">{{ $stats['active_customers'] }}</div>
            </div>
        </div>

        <h2 class="page-title">Orders by Status</h2>
        <table>
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
                @forelse ($stats['orders_by_status'] as $status => $statusCount)
                    <tr>
                        <td><span class="status-badge">{{ $status }}</span></td>
                        <td>{{ $statusCount }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
