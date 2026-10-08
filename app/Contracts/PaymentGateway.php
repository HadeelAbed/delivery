<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    public function charge(Order $order): Payment;

    /**
     * Verify a gateway callback and return the result.
     *
     * @return array{success: bool, reference_id: ?string, message: string}
     */
    public function verifyCallback(array $data): array;
}
