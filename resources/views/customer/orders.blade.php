@extends('layouts.customer')

@section('title', 'My Orders')
@section('page')
<div class="card">
    <h2>My Orders</h2>

    @if ($orders->isEmpty())
        <p>No orders yet. <a href="{{ route('customer.browse') }}">Start browsing</a></p>
    @else
        <table>
            <thead><tr><th>#</th><th>Merchant</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>{{ $order->id }}</td>
                        <td>{{ $order->merchant->merchantProfile->business_name ?? $order->merchant->name }}</td>
                        <td>{{ $order->total }}</td>
                        <td>{{ $order->status->value }}</td>
                        <td><a href="{{ route('customer.orders.show', $order) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $orders->links() }}
    @endif
</div>
@overwrite
