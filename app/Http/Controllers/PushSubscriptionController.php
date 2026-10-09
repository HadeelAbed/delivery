<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /**
     * Store (or refresh) the caller's own browser push subscription.
     *
     * Responses never include keys or secrets — only identifiers.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'endpoint' => $validated['endpoint'],
            ],
            [
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aesgcm',
            ]
        );

        return response()->json([
            'status' => 'subscribed',
            'subscription_id' => $subscription->id,
        ], 201);
    }

    /**
     * Delete one of the caller's own subscriptions by endpoint.
     * Users can only ever affect their own rows (scoped by Auth::id()).
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        $deleted = PushSubscription::where('user_id', Auth::id())
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([
            'status' => $deleted > 0 ? 'unsubscribed' : 'not_found',
        ]);
    }
}
