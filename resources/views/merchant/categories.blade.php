@extends('layouts.merchant')

@section('title', 'Categories')
@section('page')
<div class="card">
    <h2>Categories</h2>

    <form method="POST" action="{{ route('merchant.categories.store') }}" style="display:flex;gap:0.5rem;margin-bottom:1.5rem">
        @csrf
        <input type="text" name="name" placeholder="New category name" required style="margin:0">
        <button type="submit">Add</button>
    </form>

    @if ($categories->isEmpty())
        <p>No categories yet.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Products</th><th></th></tr></thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->products->count() }}</td>
                        <td>
                            <form method="POST" action="{{ route('merchant.categories.destroy', $category) }}" class="inline-form">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="secondary">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@overwrite
