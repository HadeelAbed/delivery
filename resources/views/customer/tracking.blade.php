<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking — Order #{{ $order->id }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f5f6f8; }
        header { background: #0f172a; color: #fff; padding: 0.9rem 1.5rem; }
        main { max-width: 640px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; }
        #map { width: 100%; height: 300px; background: #e2e8f0; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; color: #64748b; }
    </style>
</head>
<body>
    <header>Delivery Platform — Order #{{ $order->id }}</header>
    <main>
        <div class="card">
            <h2>Live Tracking</h2>
            <p>Status: {{ $order->status->value }}</p>
            <div id="map">Map loading…</div>
            <p id="driver-info">Waiting for driver location…</p>
        </div>
    </main>
    <script>
        async function poll() {
            try {
                const res = await fetch('{{ route('customer.orders.tracking', $order) }}');
                if (!res.ok) return;
                const data = await res.json();
                if (data.driver_location) {
                    document.getElementById('driver-info').textContent =
                        'Driver location: ' + data.driver_location.latitude + ', ' + data.driver_location.longitude +
                        ' (' + (data.driver_location.fresh ? 'fresh' : 'stale') + ')';
                }
            } catch (e) {}
        }
        poll();
        setInterval(poll, 7000);
    </script>
</body>
</html>
