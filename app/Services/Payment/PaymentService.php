<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use InvalidArgumentException;

class PaymentService
{
    public function resolve(string $method): PaymentGateway
    {
        return match ($method) {
            'cod' => new CodDriver,
            'jawwal_pay' => new JawwalDriver,
            'palpay' => new PalpayDriver,
            default => throw new InvalidArgumentException("Unknown payment method: {$method}"),
        };
    }

    public function process(Order $order): Payment
    {
        return $this->resolve($order->payment_method)->charge($order);
    }

    /**
     * Handle a gateway callback and update the payment status.
     *
     * @return array{success: bool, reference_id: ?string, message: string}
     */
    public function handleCallback(string $method, array $data): array
    {
        $result = $this->resolve($method)->verifyCallback($data);

        if (! $result['success']) {
            return $result;
        }

        $referenceId = $result['reference_id'] ?? null;

        if (! $referenceId) {
            return ['success' => false, 'reference_id' => null, 'message' => 'Missing reference ID'];
        }

        $payment = Payment::where('reference_id', $referenceId)->first();

        if (! $payment) {
            return ['success' => false, 'reference_id' => $referenceId, 'message' => 'Payment not found'];
        }

        if ($payment->status === PaymentStatus::Paid) {
            return ['success' => true, 'reference_id' => $referenceId, 'message' => 'Already processed'];
        }

        $payment->update(['status' => PaymentStatus::Paid->value]);

        return $result;
    }
}
