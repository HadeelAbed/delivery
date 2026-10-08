@extends('layouts.customer')

@section('title', 'Cart')
@section('page')
<div class="card">
    <h2>Your Cart</h2>

    @if ($items->isEmpty())
        <p>Your cart is empty. <a href="{{ route('customer.browse') }}">Browse merchants</a></p>
    @else
        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th><th></th></tr></thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['product']->name }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['product']->price }}</td>
                        <td>{{ $item['subtotal'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('customer.cart.remove', $item['product']) }}" class="inline-form">
                                @csrf
                                <button type="submit" class="secondary">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>Items total: {{ $itemsTotal }} · Delivery fee: {{ $deliveryFee }} · <strong>Total: {{ $itemsTotal + $deliveryFee }}</strong></p>
        <a href="{{ route('customer.checkout') }}"><button type="button">Proceed to checkout</button></a>
    @endif
</div>
@overwrite
