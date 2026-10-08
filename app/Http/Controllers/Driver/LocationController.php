<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        app(TrackingService::class)->recordLocation(
            Auth::id(),
            $validated['order_id'] ?? null,
            $validated['latitude'],
            $validated['longitude'],
        );

        return response()->json(['status' => 'ok']);
    }
}
