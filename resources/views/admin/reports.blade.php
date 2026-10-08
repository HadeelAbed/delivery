<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --accent-hover: #0e7490; --card: #ffffff; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--fg); margin: 0; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .filter-row { display: gap-2; margin-bottom: 20px; }
        .filter-row input { padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; }
        .filter-row select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .summary-card { background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .summary-card h3 { font-size: 14px; color: var(--muted); margin-bottom: 8px; }
        .summary-card .value { font-size: 24px; font-weight: 600; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        tr:hover td { background: #f1f5f9; }
    </style>
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="content">
        <h1 class="page-title">Reports</h1>

        <div class="filter-row">
            <form method="GET" action="/admin/reports/sales" style="gap: 8px;">
                <label>From</label>
                <input type="date" name="from" class="filter-input" value="{{ $from }}">
                <label>To</label>
                <input type="date" name="to" class="filter-input" value="{{ $to }}">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Revenue</h3>
                <div class="value">{{ number_format((float) $total, 2) }} {{ config('delivery.currency', 'ILS') }}</div>
            </div>
            <div class="summary-card">
                <h3>Orders Delivered</h3>
                <div class="value">{{ $count }}</div>
            </div>
        </div>

        @if($orders->isEmpty())
            <div style="padding: 40px; text-align: center; color: var(--muted);">No orders for the selected date.</div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Merchant</th>
                            <th>Total</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="/admin/orders/{{ $order->id }}">{{ $order->id }}</a></td>
                                <td>{{ $order->customer->name }}</td>
                                <td>{{ $order->merchant->name }}</td>
                                <td>{{ number_format($order->total, 2) }} {{ config('delivery.currency', 'ILS') }}</td>
                                <td>{{ $order->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>