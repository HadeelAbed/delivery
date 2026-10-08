@extends('layouts.customer')

@section('title', $merchant->merchantProfile->business_name ?? $merchant->name)
@section('page')
<div class="card">
    <h2>{{ $merchant->merchantProfile->business_name ?? $merchant->name }}</h2>
    <p style="color:#64748b">{{ $merchant->merchantProfile->address ?? '' }}</p>

    @foreach ($categories as $category)
        <h3>{{ $category->name }}</h3>
        <table>
            <thead><tr><th>Item</th><th>Price</th><th></th></tr></thead>
            <tbody>
                @foreach ($category->products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->price }}</td>
                        <td>
                            <form method="POST" action="{{ route('customer.cart.add') }}" class="inline-form">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="number" name="quantity" value="1" min="1" max="99" style="width:3.5rem;display:inline;padding:0.25rem">
                                <button type="submit">Add</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</div>
@overwrite
