@extends('layouts.merchant')

@section('title', 'Merchant Dashboard')
@section('page')
<div class="card">
    <h2>Merchant Dashboard</h2>
    <p>Welcome, {{ Auth::user()->name }}. Your business: <strong>{{ Auth::user()->merchantProfile->business_name ?? '—' }}</strong></p>

    <h3>Recent orders</h3>
    @if ($recentOrders->isEmpty())
        <p>No orders yet.</p>
    @else
        <table>
            <thead>
                <tr><th>#</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th></tr>
            </thead>
            <tbody>
                @foreach ($recentOrders as $order)
                    <tr>
                        <td>{{ $order->id }}</td>
                        <td>{{ $order->customer->name }}</td>
                        <td>{{ $order->status->value }}</td>
                        <td>{{ $order->total }}</td>
                        <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@overwrite
