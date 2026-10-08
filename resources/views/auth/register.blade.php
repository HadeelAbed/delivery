@extends('layouts.app')

@section('title', 'Register')
@section('content')
<div class="card" style="max-width:28rem;margin:0 auto">
    <h2>Create account</h2>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required>
        @error('name')<p class="err">{{ $message }}</p>@enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<p class="err">{{ $message }}</p>@enderror

        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone') }}" required>
        @error('phone')<p class="err">{{ $message }}</p>@enderror

        <label for="role">I am a…</label>
        <select id="role" name="role">
            <option value="customer" {{ old('role') === 'customer' ? 'selected' : '' }}>Customer</option>
            <option value="merchant" {{ old('role') === 'merchant' ? 'selected' : '' }}>Restaurant / Store owner</option>
            <option value="driver" {{ old('role') === 'driver' ? 'selected' : '' }}>Delivery driver</option>
        </select>
        @error('role')<p class="err">{{ $message }}</p>@enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>
        @error('password')<p class="err">{{ $message }}</p>@enderror

        <button type="submit">Register</button>
    </form>
    <p style="margin-top:1rem">Already registered? <a href="{{ route('login') }}">Login</a></p>
</div>
@overwrite
