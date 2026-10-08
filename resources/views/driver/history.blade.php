@extends('layouts.driver')

@section('title', __('driver.history'))
@section('page')
<div class="card">
    <h2>{{ __('driver.history') }}</h2>
    @if ($history->isEmpty())
        <p>{{ __('driver.no_history') }}</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Order</th><th>Delivered</th><th></th></tr></thead>
            <tbody>
                @foreach ($history as $delivery)
                    <tr>
                        <td>{{ $delivery->id }}</td>
                        <td data-order="order-{{ $delivery->order->id }}">#{{ $delivery->order->id }}</td>
                        <td>{{ $delivery->delivered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td><a href="{{ route('driver.deliveries.show', $delivery) }}">{{ __('driver.view') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $history->links() }}
    @endif
</div>
@overwrite
