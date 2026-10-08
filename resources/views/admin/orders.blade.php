<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin</title>
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
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #d1fae5; color: #055a32; }
        .status-ready { background: #a3e635; color: #166534; }
        .status-delivered { background: #d1d5db; color: #374151; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }
        .no-orders { padding: 40px; text-align: center; color: var(--muted); }
        .btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-secondary { background: var(--border); color: var(--fg); }
    </style>
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="content">
        <h1 class="page-title">Orders</h1>

        <div class="filter-row">
            <form method="GET" action="/admin/orders" style="gap: 8px;">
                <select name="status" class="filter-input">
                    <option value="">All Statuses</option>
                    @foreach (App\Enums\OrderStatus::cases() as $statusOption)
                        <option value="{{ $statusOption->value }}" {{ ($filters['status'] ?? '') === $statusOption->value ? 'selected' : '' }}>{{ $statusOption->value }}</option>
                    @endforeach
                </select>
                <select name="merchant_id" class="filter-input">
                    <option value="">All Merchants</option>
                    @foreach ($merchants as $m)
                        <option value="{{ $m->id }}" {{ ($filters['merchant_id'] ?? '') == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                    @endforeach
                </select>
                <select name="customer_id" class="filter-input">
                    <option value="">All Customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        @if($orders->isEmpty())
            <div class="no-orders">No orders found.</div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Merchant</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="/admin/orders/{{ $order->id }}">{{ $order->id }}</a></td>
                                <td>{{ $order->customer->name }}</td>
                                <td>{{ $order->merchant->name }}</td>
                                <td><span class="status-badge status-{{ strtolower(str_replace([' ', '-'], '', $order->status->value)) }}">{{ $order->status->value }}</span></td>
                                <td>{{ number_format($order->total, 2) }} {{ config('delivery.currency', 'ILS') }}</td>
                                <td>{{ $order->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="/admin/orders/{{ $order->id }}" class="btn btn-secondary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        @endif
    </div>
</body>
</html>