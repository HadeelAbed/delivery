<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

class PalpayDriver implements PaymentGateway
{
    public function charge(Order $order): Payment
    {
        $referenceId = 'PP-'.uniqid();

        return Payment::create([
            'order_id' => $order->id,
            'method' => 'palpay',
            'status' => PaymentStatus::Pending->value,
            'reference_id' => $referenceId,
        ]);
    }

    public function verifyCallback(array $data): array
    {
        $signature = $data['signature'] ?? '';
        $payload = $data['payload'] ?? [];
        $expected = hash_hmac('sha256', json_encode($payload), (string) config('payment.palpay_secret'));

        if (! hash_equals($expected, $signature)) {
            return [
                'success' => false,
                'reference_id' => null,
                'message' => 'Invalid signature',
            ];
        }

        return [
            'success' => true,
            'reference_id' => $data['reference_id'] ?? null,
            'message' => 'Payment verified',
        ];
    }
}
