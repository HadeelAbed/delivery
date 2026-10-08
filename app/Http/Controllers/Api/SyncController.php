<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actions' => ['required', 'array'],
            'actions.*.client_uuid' => ['required', 'string'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.payload' => ['sometimes', 'array'],
            'actions.*.at' => ['nullable', 'string'],
        ]);

        $result = app(SyncService::class)->processBatch(Auth::user(), $validated['actions']);

        return response()->json($result);
    }
}
