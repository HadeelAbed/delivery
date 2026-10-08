@extends('layouts.merchant')

@section('title', 'Products')
@section('page')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2>Products</h2>
        <a href="{{ route('merchant.products.create') }}"><button type="button">Add product</button></a>
    </div>

    @if ($products->isEmpty())
        <p>No products yet.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Available</th><th></th></tr></thead>
            <tbody>
                @foreach ($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category->name ?? '—' }}</td>
                        <td>{{ $product->price }}</td>
                        <td>{{ $product->is_available ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('merchant.products.edit', $product) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@overwrite
