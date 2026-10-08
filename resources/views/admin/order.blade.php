<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --accent-hover: #0e7490; --card: #ffffff; --border: #e2e8f0; --danger: #ef4444; --warning: #f59e0b; --success: #10b981; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--fg); margin: 0; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .back-link { margin-bottom: 24px; }
        .back-link a { color: var(--muted); text-decoration: none; }
        .back-link a:hover { color: var(--accent); }
        .order-info { background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 24px; }
        .order-info p { margin: 8px 0; }
        .order-info p strong { color: var(--muted); }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #d1fae5; color: #055a32; }
        .status-ready { background: #a3e635; color: #166534; }
        .status-delivered { background: #d1d5db; color: #374151; }
        .status-cancelled { background: #fee2e2; color: #b91c1c; }
        .delivery-info { background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .delivery-info p { margin: 8px 0; }
        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline li { padding: 12px 0; border-top: 1px solid var(--border); }
        .timeline-item { display: flex; justify-content: space-between; align-items: flex-start; }
        .timeline-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); flex-shrink: 0; margin-right: 12px; }
    </style>
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="content">
        <h1 class="page-title">Order #{{ $order->id }}</h1>

        <div class="back-link">
            <a href="/admin/orders">&larr; Back to Orders</a>
        </div>

        <div class="order-info">
            <p><strong>Customer:</strong> {{ $order->customer->name }} ({{ $order->customer->email }})</p>
            <p><strong>Merchant:</strong> {{ $order->merchant->name }}</p>
            <p><strong>Total:</strong> {{ number_format($order->total, 2) }} {{ config('delivery.currency', 'ILS') }}</p>
            <p><strong>Status:</strong> <span class="status-badge status-{{ strtolower(str_replace([' ', '-'], '', $order->status)) }}">{{ $order->status }}</span></p>
            <p><strong>Created:</strong> {{ $order->created_at->format('M d, Y H:i') }}</p>
            <p><strong>Payment Method:</strong> {{ $order->payment_method ?? 'Not set' }}</p>
        </div>

        @if($order->delivery)
            <div class="delivery-info">
                <h3>Delivery</h3>
                <p><strong>Assigned Driver:</strong> {{ $order->driver ? $order->driver->user->name : 'None' }}</p>
                <p><strong>Current Status:</strong> {{ $order->delivery->status }}</p>
                @if($order->delivery->current_location)
                    <p><strong>Location:</strong> {{ $order->delivery->current_location }}</p>
                @endif
            </div>
        @endif

        <h3 class="mt-6">Timeline</h3>
        <ul class="timeline">
            @foreach($order->statusHistory as $entry)
                <li>
                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div>
                            <strong>{{ $entry->action }}</strong> at {{ $entry->created_at->format('H:i') }}
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</body>
</html>