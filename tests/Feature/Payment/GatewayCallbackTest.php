<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(string $method): Order
    {
        $customer = User::factory()->create();
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        Merchant::create(['user_id' => $merchant->id, 'business_name' => 'Cafe', 'address' => 'Gaza']);

        $order = Order::create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 25.00,
            'delivery_fee' => 15.00,
            'total' => 40.00,
            'delivery_address' => ['label' => 'Gaza', 'lat' => 31.5, 'lng' => 34.47],
            'payment_method' => $method,
        ]);

        app(PaymentService::class)->process($order);

        return $order;
    }

    public function test_valid_callback_marks_payment_paid(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');
        $payment = $order->payment;

        $payload = ['order_id' => $order->id, 'amount' => 40.00];
        $signature = hash_hmac('sha256', json_encode($payload), (string) config('payment.jawwal_secret'));

        $result = app(PaymentService::class)->handleCallback('jawwal_pay', [
            'payload' => $payload,
            'signature' => $signature,
            'reference_id' => $payment->reference_id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');
        $payment = $order->payment;

        $payload = ['order_id' => $order->id, 'amount' => 40.00];
        $signature = hash_hmac('sha256', json_encode($payload), (string) config('payment.jawwal_secret'));

        $data = [
            'payload' => $payload,
            'signature' => $signature,
            'reference_id' => $payment->reference_id,
        ];

        app(PaymentService::class)->handleCallback('jawwal_pay', $data);
        $result = app(PaymentService::class)->handleCallback('jawwal_pay', $data);

        $this->assertTrue($result['success']);
        $this->assertSame('Already processed', $result['message']);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');
        $payment = $order->payment;

        $result = app(PaymentService::class)->handleCallback('jawwal_pay', [
            'payload' => ['order_id' => $order->id],
            'signature' => 'bad-signature',
            'reference_id' => $payment->reference_id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_payment_schema_has_no_sensitive_columns(): void
    {
        $columns = \Schema::getColumnListing('payments');

        $this->assertNotContains('card_number', $columns);
        $this->assertNotContains('cvv', $columns);
        $this->assertNotContains('pan', $columns);
    }
}
