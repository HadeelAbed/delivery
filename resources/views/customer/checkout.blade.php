@extends('layouts.customer')

@section('title', 'Checkout')
@section('page')
<div class="card" style="max-width:34rem;margin:0 auto">
    <h2>Checkout</h2>
    <form method="POST" action="{{ route('customer.checkout.place') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}\">

        <label for="address_label">Delivery address</label>
        <input id="address_label" name="address[label]" placeholder="e.g. Gaza, Al-Rimal, Building 5" required>
        @error('address.label')<p class="err">{{ $message }}</p>@enderror

        <label for="address_lat">Latitude</label>
        <input id="address_lat" type="number" step="any" name="address[lat]" required>
        @error('address.lat')<p class="err">{{ $message }}</p>@enderror

        <label for="address_lng">Longitude</label>
        <input id="address_lng" type="number" step="any" name="address[lng]" required>
        @error('address.lng')<p class="err">{{ $message }}</p>@enderror

        <label for="payment_method">Payment method</label>
        <select id="payment_method" name="payment_method" required>
            <option value="cod">Cash on Delivery</option>
            <option value="jawwal_pay">Jawwal Pay</option>
            <option value="palpay">PalPay</option>
        </select>
        @error('payment_method')<p class="err">{{ $message }}</p>@enderror

        <button type="submit">Place order</button>
    </form>
</div>
@overwrite
