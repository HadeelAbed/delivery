<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('customer.favorites') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --card: #ffffff; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--fg); margin: 0; direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .no-favorites { padding: 40px; text-align: center; color: var(--muted); direction: ltr; }
        .fav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 24px; }
        .fav-card { background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); transition: transform 0.2s; }
        .fav-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .fav-image { width: 100%; height: 150px; border-radius: 4px; background: #f1f5f9; object-fit: cover; margin-bottom: 12px; }
        .fav-name { font-weight: 500; margin-bottom: 4px; }
        .fav-details { font-size: 13px; color: var(--muted); }
        .btn-ghost { background: transparent; border: none; padding: 0; cursor: pointer; color: var(--muted); font-size: 13px; }
        .btn-ghost:hover { color: var(--accent); }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="/customer" class="<?= request()->path() === '/customer' || request()->path() === '/customer/' ? 'active' : '' ?>">{{ __('customer.home') }}</a>
        <a href="/customer/orders" class="<?= request()->path() === '/customer/orders' ? 'active' : '' ?>">{{ __('customer.orders') }}</a>
        <a href="/customer/profile" class="<?= request()->path() === '/customer/profile' ? 'active' : '' ?>">{{ __('customer.profile') }}</a>
        <a href="/customer/favorites" class="<?= request()->path() === '/customer/favorites' ? 'active' : '' ?>">{{ __('customer.favorites') }}</a>
    </div>
    <div class="content">
        <h1 class="page-title">{{ __('customer.favorites') }}</h1>

        @if($favorites->isEmpty())
            <div class="no-favorites">{{ __('customer.no_favorites') }}</div>
        @else
            <div class="fav-grid">
                @foreach ($favorites as $merchant)
                    <div class="fav-card">
                        <img src="{{ asset('storage/' . $merchant->logo) }}" alt="{{ $merchant->business_name }}" class="fav-image">
                        <div>
                            <div class="fav-name">{{ $merchant->business_name }}</div>
                            <div class="fav-details">{{ $merchant->user->name }}</div>
                        </div>
                        <button class="btn-ghost" onclick="toggleFavorite({{ $merchant->id }})">
                            {{ $favoritesContains($merchant->id) ? __('customer.removed_from_favorites') : __('customer.add_to_favorites') }}
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <a href="/customer/browse" class="btn btn-secondary">{{ __('customer.browse_merchants') }}</a>
        </div>
    </div>

    <script>
        function toggleFavorite(merchantId) {
            const btn = event.currentTarget;
            const isAdded = btn.textContent === '{{ __('customer.add_to_favorites') }}';
            
            fetch('/customer/favorites/' + merchantId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (isAdded) {
                    btn.textContent = '{{ __('customer.removed_from_favorites') }}';
                } else {
                    btn.textContent = '{{ __('customer.add_to_favorites') }}';
                }
            });
        }
    </script>
</body>
</html>