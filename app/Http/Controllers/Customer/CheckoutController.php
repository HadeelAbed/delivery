<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function show()
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        if (empty($cart['items'])) {
            return redirect()->route('customer.browse');
        }

        return view('customer.checkout', compact('cart'));
    }

    public function place(PlaceOrderRequest $request)
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        if (empty($cart['items'])) {
            return redirect()->route('customer.browse');
        }

        $merchant = User::findOrFail($cart['merchant_id']);

        $order = app(OrderService::class)->place(
            Auth::user(),
            $merchant,
            $cart['items'],
            $request->validated()['address'],
            $request->validated()['payment_method'],
        );

        session(['cart' => ['merchant_id' => null, 'items' => []]]);

        return redirect()->route('customer.orders.show', $order)
            ->with('status', 'Order placed successfully.');
    }
}
