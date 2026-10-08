<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('customer.profile') }}</title>
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
        .filter-row { display: gap-2; margin-bottom: 20px; }
        .filter-row input { padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        .no-orders { padding: 40px; text-align: center; color: var(--muted); }
        .btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-secondary { background: var(--border); color: var(--fg); }
        .fav-chip { background: #e2e8f0; padding: 6px 12px; border-radius: 20px; font-size: 12px; margin: 4px; display: inline-block; }
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
        <h1 class="page-title">{{ __('customer.profile') }}</h1>

        <div class="filter-row">
            <form method="GET" action="/customer/profile" style="gap: 8px;">
                <input type="text" name="search" placeholder="{{ __('customer.search') }}" value="{{ $search ?? '' }}">
                <button type="submit" class="btn btn-primary">{{ __('customer.search') }}</button>
            </form>
        </div>

        @if($user)
            <div class="card-bg p-6 mb-6">
                <h3 class="font-semibold mb-2">{{ __('customer.your_profile') }}</h3>
                <p><strong>{{ __('customer.name') }}:</strong> {{ $user->name }}</p>
                <p><strong>{{ __('customer.email') }}:</strong> {{ $user->email }}</p>
                <p><strong>{{ __('customer.phone') }}:</strong> {{ $user->phone ?? 'Not provided' }}</p>
                <p><strong>{{ __('customer.role') }}:</strong> {{ ucfirst(str_replace('_', ' ', $user->role->value)) }}</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h3 class="font-semibold mb-4">{{ __('customer.profile') }} {{ __('customer.edit_profile') }}</h3>
                <form action="/customer/profile" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium mb-2">{{ __('customer.name') }}</label>
                        <input type="text" name="name" value="{{ $user->name }}" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">{{ __('customer.email') }}</label>
                        <input type="email" name="email" value="{{ $user->email }}" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">{{ __('customer.phone') }}</label>
                        <input type="tel" name="phone" value="{{ $user->phone ?? '' }}" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">{{ __('customer.save_changes') }}</button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h3 class="font-semibold mb-4">{{ __('customer.deactivate_account') }}</h3>
                <p class="text-sm text-gray-600 mb-3">{{ __('customer.deactivate_confirm') }}</p>
                <form action="{{ route('account.deactivate') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full" onclick="return confirm('{{ __('customer.deactivate_confirm') }}')">{{ __('customer.deactivate_account') }}</button>
                </form>
            </div>

            @if(session('status'))
                <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('status') }}</div>
            @endif
        @endif

    </div>
</body>
</html>