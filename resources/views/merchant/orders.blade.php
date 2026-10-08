@extends('layouts.merchant')

@section('title', 'Orders')
@section('page')
<div class="card">
    <h2>Orders</h2>

    @if ($orders->isEmpty())
        <p>No orders yet.</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>{{ $order->id }}</td>
                        <td>{{ $order->customer->name }}</td>
                        <td>{{ $order->items->sum('quantity') }}</td>
                        <td>{{ $order->total }}</td>
                        <td>{{ $order->status->value }}</td>
                        <td><a href="{{ route('merchant.orders.show', $order) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $orders->links() }}
    @endif
</div>
@overwrite
