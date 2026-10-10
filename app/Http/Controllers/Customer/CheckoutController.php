<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function show()
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        if (empty($cart['items'])) {
            return redirect()->route('customer.browse');
        }

        $idempotencyKey = session('checkout.idempotency_key');

        if (! $idempotencyKey) {
            $idempotencyKey = (string) Str::uuid();
            session(['checkout.idempotency_key' => $idempotencyKey]);
        }

        return view('customer.checkout', compact('cart', 'idempotencyKey'));
    }

    public function place(PlaceOrderRequest $request)
    {
        $cart = session('cart', ['merchant_id' => null, 'items' => []]);

        if (empty($cart['items'])) {
            return redirect()->route('customer.browse');
        }

        $merchant = User::findOrFail($cart['merchant_id']);
        $idempotencyKey = $request->validated()['idempotency_key'] ?? session('checkout.idempotency_key');

        try {
            $order = app(OrderService::class)->place(
                Auth::user(),
                $merchant,
                $cart['items'],
                $request->validated()['address'],
                $request->validated()['payment_method'],
                $idempotencyKey,
            );
        } catch (UniqueConstraintViolationException $e) {
            // A duplicate/concurrent submission carrying the same idempotency key
            // already created this order. Return the existing order instead of
            // creating a duplicate. Scoped to the authenticated customer so a user
            // can never receive another customer's order.
            $order = Order::where('customer_id', Auth::id())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $order) {
                throw $e;
            }
        }

        session(['cart' => ['merchant_id' => null, 'items' => []]]);
        session()->forget('checkout.idempotency_key');

        return redirect()->route('customer.orders.show', $order)
            ->with('status', 'Order placed successfully.');
    }
}
