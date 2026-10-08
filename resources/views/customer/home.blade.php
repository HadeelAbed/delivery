@extends('layouts.customer')

@section('title', 'Customer Home')
@section('page')
<div class="card">
    <h2>Welcome, {{ Auth::user()->name }}</h2>
    <p>Browse restaurants, cafés, grocery stores and pharmacies near you. Ordering and live delivery tracking will be available in the next milestone (M2–M3).</p>
</div>
@overwrite
