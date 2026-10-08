<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Models\Product;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        $items = collect($cart['items'])->map(function ($item) {
            $product = Product::find($item['product_id']);

            return $product ? [
                'product' => $product,
                'quantity' => $item['quantity'],
                'subtotal' => (float) $product->price * $item['quantity'],
            ] : null;
        })->filter();

        $itemsTotal = $items->sum('subtotal');
        $deliveryFee = (float) config('delivery.delivery_fee', 15.00);

        return view('customer.cart', compact('items', 'itemsTotal', 'deliveryFee'));
    }

    public function add(AddToCartRequest $request)
    {
        $product = Product::findOrFail($request->validated()['product_id']);
        abort_unless($product->is_available, 422, 'Product is not available.');

        $merchantId = $product->category->merchant_id;
        $qty = $request->validated()['quantity'];

        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        if ($cart['merchant_id'] !== null && $cart['merchant_id'] !== $merchantId) {
            return back()->with('error', 'Your cart can only contain items from one merchant.');
        }

        $cart['merchant_id'] = $merchantId;

        $existing = collect($cart['items'])->firstWhere('product_id', $product->id);

        if ($existing) {
            $cart['items'] = collect($cart['items'])
                ->map(fn ($i) => $i['product_id'] === $product->id
                    ? [...$i, 'quantity' => $i['quantity'] + $qty]
                    : $i)
                ->values()
                ->all();
        } else {
            $cart['items'][] = ['product_id' => $product->id, 'quantity' => $qty];
        }

        session(['cart' => $cart]);

        return back()->with('status', 'Added to cart.');
    }

    public function remove(Product $product)
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        $cart['items'] = collect($cart['items'])
            ->reject(fn ($i) => $i['product_id'] === $product->id)
            ->values()
            ->all();

        session(['cart' => $cart]);

        return back()->with('status', 'Removed from cart.');
    }
}
