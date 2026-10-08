@extends('layouts.driver')

@section('title', __('driver.dashboard'))
@section('page')
<div class="card">
    <h2>{{ __('driver.dashboard') }}</h2>
    <p>{{ __('driver.completed') }}: <strong>{{ $driverStats['completed'] }}</strong> ·
       {{ __('driver.earnings') }}: <strong>{{ $driverStats['earnings'] }} {{ $driverStats['currency'] }}</strong> ·
       {{ __('driver.rating') }}: <strong>{{ $driverStats['rating'] ?? '—' }}</strong> ·
       {{ __('driver.active') }}: <strong>{{ $driverStats['active'] }}</strong></p>
    <p><a href="{{ route('driver.deliveries') }}">{{ __('driver.incoming') }}</a> ·
       <a href="{{ route('driver.deliveries.history') }}">{{ __('driver.history') }}</a></p>
</div>
@overwrite
