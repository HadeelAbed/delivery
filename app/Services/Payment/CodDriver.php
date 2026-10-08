<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

class CodDriver implements PaymentGateway
{
    public function charge(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => PaymentStatus::PendingCod->value,
        ]);
    }

    public function verifyCallback(array $data): array
    {
        return [
            'success' => true,
            'reference_id' => null,
            'message' => 'COD — no callback needed',
        ];
    }
}
