@extends('layouts.driver')

@section('title', __('driver.incoming'))
@section('page')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h2>{{ __('driver.incoming') }}</h2>
        <form method="POST" action="{{ route('driver.toggle-online') }}" class="inline-form">
            @csrf
            <button type="submit" class="{{ Auth::user()->is_online ? 'secondary' : '' }}">
                {{ Auth::user()->is_online ? 'Go Offline' : 'Go Online' }}
            </button>
        </form>
    </div>

    <h3>{{ __('driver.incoming') }} ({{ $incoming->count() }})</h3>
    @if ($incoming->isEmpty())
        <p>{{ __('driver.no_incoming') }}</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Order</th><th>{{ __('driver.earnings') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($incoming as $delivery)
                    <tr>
                        <td>{{ $delivery->id }}</td>
                        <td>#{{ $delivery->order->id }}</td>
                        <td>{{ $perOrderEarnings }} {{ $currency }} {{ __('driver.per_delivery') }}</td>
                        <td><a href="{{ route('driver.deliveries.show', $delivery) }}">{{ __('driver.view') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ __('driver.active') }} ({{ $activeDeliveries->count() }})</h3>
    @if ($activeDeliveries->isEmpty())
        <p>{{ __('driver.no_active') }}</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Order</th><th></th></tr></thead>
            <tbody>
                @foreach ($activeDeliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->id }}</td>
                        <td>#{{ $delivery->order->id }}</td>
                        <td><a href="{{ route('driver.deliveries.show', $delivery) }}">{{ __('driver.view') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p><a href="{{ route('driver.deliveries.history') }}">{{ __('driver.history') }}</a></p>
</div>
@overwrite
