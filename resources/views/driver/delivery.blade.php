@extends('layouts.driver')

@section('title', 'Delivery #'.$delivery->id)
@section('page')
<div class="card">
    <h2>Delivery #{{ $delivery->id }}</h2>
    <p>Order #{{ $delivery->order->id }} · Status: {{ $delivery->status }}</p>

    <h3>Pickup</h3>
    <p>Merchant: <strong>{{ $delivery->order->merchant->merchantProfile->business_name ?? $delivery->order->merchant->name }}</strong></p>
    <p>Address: {{ $delivery->order->merchant->merchantProfile->address ?? '—' }}</p>

    <h3>Drop-off</h3>
    <p>Customer: <strong>{{ $delivery->order->customer->name }}</strong> · {{ $delivery->order->customer->phone }}</p>
    <p>Address: {{ $delivery->order->delivery_address['label'] ?? '—' }}</p>

    <h3>Items</h3>
    <table>
        <thead><tr><th>Item</th><th>Qty</th></tr></thead>
        <tbody>
            @foreach ($delivery->order->items as $item)
                <tr><td>{{ $item->product->name }}</td><td>{{ $item->quantity }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p>Earnings: <strong>{{ config('delivery.driver_earnings', 10) }}</strong></p>

    @if ($delivery->status === 'assigned')
        <div style="display:flex;gap:1rem;margin-top:1rem">
            <form method="POST" action="{{ route('driver.deliveries.pickup', $delivery) }}">
                @csrf
                <button type="submit">Confirm Pickup</button>
            </form>
            <form method="POST" action="{{ route('driver.deliveries.reject', $delivery) }}">
                @csrf
                <button type="submit" class="secondary">Reject</button>
            </form>
        </div>
    @elseif ($delivery->status === 'out_for_delivery')
        <form method="POST" action="{{ route('driver.deliveries.deliver', $delivery) }}" style="margin-top:1rem">
            @csrf
            <button type="submit">Confirm Delivery</button>
        </form>
    @endif
</div>
@overwrite
