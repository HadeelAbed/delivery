<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('customer.rating') }} - Order #{{ $order->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --card: #ffffff; --border: #e2e8f0; --success: #10b981; --error: #ef4444; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--fg); margin: 0; direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        .no-orders { padding: 40px; text-align: center; color: var(--muted); }
        .btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-secondary { background: var(--border); color: var(--fg); }
        .rating-stars { display: inline-flex; gap: 4px; }
        .rating-stars input { display: none; }
        .rating-stars label {
            width: 30px; height: 30px; background: #e2e8f0; border-radius: 4px; 
            display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer;
            transition: color 0.2s; color: #a0a0a0;
        }
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ input { color: #f5a623; }
        .rating-stars label:hover ~ label { color: #e2e8f0; }
        .form-control { padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; }
        .alert { padding: 10px; border-radius: 6px; margin: 10px 0; }
        .alert-success { background: #d1fae5; color: #055a32; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
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
        <h1 class="page-title">{{ __('customer.rating') }} - Order #{{ $order->id }}</h1>

        @if(session('type') === 'error')
            <div class="alert alert-error">{{ session('status') }}</div>
        @elseif(session('type') === 'success')
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($canRate['canRate'] ?? false)
            <p class="text-sm text-muted mb-4">{{ __('customer.order_must_be_delivered_to_rate') }}: <strong>{{ $order->status }}</strong></p>

            <form action="/customer/orders/{{ $order->id }}/rate" method="POST" class="space-y-4">
                @method('POST')
                @csrf

                <input type="hidden" name="order_id" value="{{ $order->id }}">

                <div class="mb-4">
                    <label>{{ __('customer.rate_merchant') }} (1-5)</label>
                    <div class="rating-stars" id="merchant-stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <label>
                                <input type="radio" name="merchant_score" value="{{ $i }}" 
                                    {{ isset($merchant_score) && $merchant_score == $i ? 'checked' : '' }}>
                                <span>&#9733;</span>
                            </label>
                        @endfor>
                    </div>
                    <small class="text-muted">1 = Poor, 5 = Excellent</small>
                </div>

                <div class="mb-4">
                    <label>{{ __('customer.rate_driver') }} (1-5)</label>
                    <div class="rating-stars" id="driver-stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <label>
                                <input type="radio" name="driver_score" value="{{ $i }}" 
                                    {{ isset($driver_score) && $driver_score == $i ? 'checked' : '' }}>
                                <span>&#9733;</span>
                            </label>
                        @endfor>
                    </div>
                    <small class="text-muted">1 = Poor, 5 = Excellent</small>
                </div>

                <div>
                    <label>{{ __('customer.written_comment') }} (optional)</label>
                    <textarea name="comment" rows="3" class="form-control" placeholder="{{ __('customer.comment_placeholder') }}">{{ old('comment') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('customer.submit_rating') }}</button>
            </form>
        @else
            <p class="text-muted">{{ __('customer.cannot_rate_yet') }}</p>
            <p class="mt-2 small"><strong>{{ __('customer.order_status') }}:</strong> {{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</p>
        @endif
    </div>

    <script>
        // Simple star rating highlight
        document.querySelectorAll('.rating-stars input').forEach(star => {
            star.addEventListener('change', function() {
                const stars = this.closest('.rating-stars').querySelectorAll('label');
                stars.forEach((label, idx) => {
                    label.style.color = idx < this.value - 1 ? '#f5a623' : '#e2e8f0';
                });
            });
        });
    </script>
</body>
</html>