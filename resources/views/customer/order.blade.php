@extends('layouts.customer')

@section('title', 'Order #'.$order->id)
@section('page')
<div class="card">
    <h2>Order #{{ $order->id }}</h2>
    <p>Merchant: <strong>{{ $order->merchant->merchantProfile->business_name ?? $order->merchant->name }}</strong></p>
    <p>Status: {{ $order->status->value }} · Payment: {{ $order->payment_method }}</p>

    <h3>Items</h3>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->unit_price }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Items total: {{ $order->items_total }} · Delivery fee: {{ $order->delivery_fee }} · <strong>Total: {{ $order->total }}</strong></p>
</div>
@overwrite
