@extends('layouts.merchant')

@section('title', 'Edit Product')
@section('page')
<div class="card" style="max-width:32rem;margin:0 auto">
    <h2>Edit Product</h2>
    <form method="POST" action="{{ route('merchant.products.update', $product) }}">
        @csrf
        @method('PUT')

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" {{ $product->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')<p class="err">{{ $message }}</p>@endforeach

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $product->name) }}" required>
        @error('name')<p class="err">{{ $message }}</p>@endforeach

        <label for="description">Description</label>
        <input id="description" name="description" value="{{ old('description', $product->description) }}">
        @error('description')<p class="err">{{ $message }}</p>@endforeach

        <label for="price">Price</label>
        <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required>
        @error('price')<p class="err">{{ $message }}</p>@endforeach

        <label for="image">Image URL</label>
        <input id="image" name="image" value="{{ old('image', $product->image) }}">
        @error('image')<p class="err">{{ $message }}</p>@endforeach

        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_available" value="1" {{ $product->is_available ? 'checked' : '' }} style="width:auto;margin:0"> Available
        </label>

        <button type="submit">Update product</button>
    </form>
</div>
@overwrite
