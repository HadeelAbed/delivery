@extends('layouts.customer')

@section('title', 'Browse Merchants')
@section('page')
<div class="card">
    <h2>Merchants near you</h2>

    @if ($merchants->isEmpty())
        <p>No merchants available yet.</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-top:1rem">
            @foreach ($merchants as $merchant)
                <a href="{{ route('customer.merchants.show', $merchant) }}" style="text-decoration:none;color:inherit">
                    <div class="card" style="padding:1rem">
                        <h3 style="margin:0 0 0.5rem">{{ $merchant->merchantProfile->business_name ?? $merchant->name }}</h3>
                        <p style="margin:0;font-size:0.9rem;color:#64748b">{{ $merchant->merchantProfile->address ?? '' }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@overwrite
