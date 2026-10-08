@extends('layouts.merchant')

@section('title', 'Business Profile')
@section('page')
<div class="card" style="max-width:36rem;margin:0 auto">
    <h2>Business Profile</h2>
    <form method="POST" action="{{ route('merchant.profile.update') }}">
        @csrf
        @method('PUT')

        <label for="business_name">Business name</label>
        <input id="business_name" name="business_name" value="{{ old('business_name', $profile->business_name) }}" required>
        @error('business_name')<p class="err">{{ $message }}</p>@enderror

        <label for="phone">Contact phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $profile->phone) }}">
        @error('phone')<p class="err">{{ $message }}</p>@enderror

        <label for="address">Address</label>
        <input id="address" name="address" value="{{ old('address', $profile->address) }}" required>
        @error('address')<p class="err">{{ $message }}</p>@enderror

        <label for="latitude">Latitude</label>
        <input id="latitude" name="latitude" value="{{ old('latitude', $profile->latitude) }}" step="any">
        @error('latitude')<p class="err">{{ $message }}</p>@enderror

        <label for="longitude">Longitude</label>
        <input id="longitude" name="longitude" value="{{ old('longitude', $profile->longitude) }}" step="any">
        @error('longitude')<p class="err">{{ $message }}</p>@enderror

        <label for="delivery_coverage">Delivery coverage</label>
        <input id="delivery_coverage" name="delivery_coverage" value="{{ old('delivery_coverage', $profile->delivery_coverage) }}">
        @error('delivery_coverage')<p class="err">{{ $message }}</p>@enderror

        <button type="submit">Save profile</button>
    </form>
</div>
@overwrite
