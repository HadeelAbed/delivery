@extends('layouts.merchant')

@section('title', 'Order #'.$order->id)
@section('page')
<div class="card">
    <h2>Order #{{ $order->id }}</h2>
    <p>Customer: <strong>{{ $order->customer->name }}</strong> · {{ $order->customer->phone }}</p>
    <p>Address: {{ $order->delivery_address['label'] ?? '—' }}</p>
    <p>Payment: {{ $order->payment_method }} · Status: {{ $order->status->value }}</p>

    <h3>Items</h3>
    <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->unit_price }}</td>
                    <td>{{ $item->quantity * $item->unit_price }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Items total: {{ $order->items_total }} · Delivery fee: {{ $order->delivery_fee }} · <strong>Total: {{ $order->total }}</strong></p>

    @if ($order->status->value === 'pending')
        <div style="display:flex;gap:1rem;margin-top:1rem;align-items:flex-end">
            <form method="POST" action="{{ route('merchant.orders.accept', $order) }}">
                @csrf
                <label for="prep_time_minutes">Prep time (minutes)</label>
                <input type="number" id="prep_time_minutes" name="prep_time_minutes" min="1" max="180" value="20" required style="width:8rem">
                <button type="submit">Accept order</button>
            </form>
            <form method="POST" action="{{ route('merchant.orders.reject', $order) }}">
                @csrf
                <label for="reason">Reason</label>
                <input type="text" id="reason" name="reason" placeholder="Reason for rejection" required style="width:14rem">
                <button type="submit" class="secondary">Reject order</button>
            </form>
        </div>
    @elseif ($order->status->value === 'merchant_accepted' || $order->status->value === 'preparing')
        <form method="POST" action="{{ route('merchant.orders.ready', $order) }}" style="margin-top:1rem">
            @csrf
            <button type="submit">Mark ready for pickup</button>
        </form>
    @endif
</div>
@overwrite
