@extends('layouts.app')

@section('title', 'Login')
@section('content')
<div class="card" style="max-width:24rem;margin:0 auto">
    <h2>Login</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<p class="err">{{ $message }}</p>@enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        @error('password')<p class="err">{{ $message }}</p>@enderror

        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="remember" style="width:auto;margin:0"> Remember me
        </label>

        <button type="submit">Login</button>
    </form>
    <p style="margin-top:1rem">No account yet? <a href="{{ route('register') }}">Register</a></p>
</div>
@overwrite
