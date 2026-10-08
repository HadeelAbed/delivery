@extends('layouts.merchant')

@section('title', 'Add Product')
@section('page')
<div class="card" style="max-width:32rem;margin:0 auto">
    <h2>Add Product</h2>
    <form method="POST" action="{{ route('merchant.products.store') }}">
        @csrf

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')<p class="err">{{ $message }}</p>@enderror

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required>
        @error('name')<p class="err">{{ $message }}</p>@enderror

        <label for="description">Description</label>
        <input id="description" name="description" value="{{ old('description') }}">
        @error('description')<p class="err">{{ $message }}</p>@enderror

        <label for="price">Price</label>
        <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" required>
        @error('price')<p class="err">{{ $message }}</p>@enderror

        <label for="image">Image URL</label>
        <input id="image" name="image" value="{{ old('image') }}">
        @error('image')<p class="err">{{ $message }}</p>@enderror

        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_available" value="1" checked style="width:auto;margin:0"> Available
        </label>

        <button type="submit">Create product</button>
    </form>
</div>
@overwrite
