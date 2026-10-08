<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentCallbackController extends Controller
{
    public function callback(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, array_keys(config('payment.callbacks', [])), true)) {
            return response()->json([
                'success' => false,
                'reference_id' => null,
                'message' => 'Unknown payment gateway',
            ], 404);
        }

        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'signature' => ['required', 'string'],
            'reference_id' => ['nullable', 'string'],
        ]);

        try {
            $result = app(PaymentService::class)->handleCallback($gateway, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'reference_id' => null,
                'message' => 'Unknown payment gateway',
            ], 404);
        }

        if ($result['success']) {
            return response()->json($result);
        }

        $status = $result['message'] === 'Payment not found' ? 404 : 422;

        return response()->json($result, $status);
    }
}
