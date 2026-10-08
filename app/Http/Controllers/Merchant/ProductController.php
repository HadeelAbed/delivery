<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function index()
    {
        $products = Auth::user()->merchantProfile?->products()->latest()->get() ?? collect();

        return view('merchant.products', compact('products'));
    }

    public function create()
    {
        $categories = Auth::user()->merchantProfile?->categories ?? collect();

        return view('merchant.products-create', compact('categories'));
    }

    public function store(ProductRequest $request)
    {
        $category = Category::findOrFail($request->validated()['category_id']);
        $this->authorize('manage', $category);

        $data = $request->validated();
        $data['is_available'] = $request->boolean('is_available', true);

        $category->products()->create($data);

        return redirect()->route('merchant.products')->with('status', 'Product created.');
    }

    public function edit(Product $product)
    {
        $this->authorize('manage', $product);
        $categories = Auth::user()->merchantProfile?->categories ?? collect();

        return view('merchant.products-edit', compact('product', 'categories'));
    }

    public function update(ProductRequest $request, Product $product)
    {
        $this->authorize('manage', $product);

        $data = $request->validated();
        $data['is_available'] = $request->boolean('is_available', $product->is_available);

        $product->update($data);

        return redirect()->route('merchant.products')->with('status', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('manage', $product);
        $product->delete();

        return redirect()->route('merchant.products')->with('status', 'Product deleted.');
    }
}
