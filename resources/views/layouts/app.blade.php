<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Delivery Platform')</title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f5f6f8; color: #1f2933; }
        header { background: #0f172a; color: #fff; padding: 0.9rem 1.5rem; display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
        header a { color: #cbd5e1; text-decoration: none; font-size: 0.95rem; }
        header a:hover { color: #fff; }
        header .brand { font-weight: 700; font-size: 1.1rem; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; }
        .flash { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1rem; }
        .error-flash { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        input, select { display: block; width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; margin: 0.25rem 0 1rem; box-sizing: border-box; }
        label { font-size: 0.9rem; font-weight: 600; }
        button { background: #2563eb; color: #fff; border: 0; padding: 0.55rem 1.1rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.95rem; }
        button.secondary { background: #475569; }
        .err { color: #b91c1c; font-size: 0.85rem; margin: -0.5rem 0 0.75rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
        .inline-form { display: inline; }
        .inline-input { display: inline; width: 11rem; padding: 0.25rem; margin: 0 0.25rem; }
    </style>
</head>
<body>
    <header>
        <span class="brand">Delivery Platform</span>
        <nav>
            @guest
                <a href="{{ route('login') }}">Login</a>
                <a href="{{ route('register') }}">Register</a>
            @else
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                    <a href="{{ route('admin.orders') }}">Orders</a>
                    <a href="{{ route('admin.users') }}">Users</a>
                    <a href="{{ route('admin.approvals') }}">Approvals</a>
                    <a href="{{ route('admin.reports.sales') }}">Reports</a>
                @elseif (Auth::user()->role->value === 'merchant')
                    <a href="{{ route('merchant.dashboard') }}">Dashboard</a>
                @elseif (Auth::user()->role->value === 'driver')
                    <a href="{{ route('driver.dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('customer.home') }}">Home</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button type="submit" class="secondary" style="padding:0.25rem 0.75rem">Logout</button>
                </form>
            @endguest
        </nav>
    </header>
    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash error-flash">
                <ul style="margin:0;padding-left:1.25rem">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
