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

class PaymentWebhookTest extends TestCase
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

    private function signedPayload(Order $order, string $secretKey): array
    {
        $payment = $order->payment;

        $payload = ['order_id' => $order->id, 'amount' => 40.00];
        $signature = hash_hmac('sha256', json_encode($payload), (string) config($secretKey));

        return [
            'payload' => $payload,
            'signature' => $signature,
            'reference_id' => $payment->reference_id,
        ];
    }

    public function test_successful_jawwal_callback_marks_payment_paid(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');

        $response = $this->postJson(
            '/api/payments/callback/jawwal_pay',
            $this->signedPayload($order, 'payment.jawwal_secret')
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(PaymentStatus::Paid, $order->payment->fresh()->status);
    }

    public function test_successful_palpay_callback_marks_payment_paid(): void
    {
        $order = $this->makeOrderWithPayment('palpay');

        $response = $this->postJson(
            '/api/payments/callback/palpay',
            $this->signedPayload($order, 'payment.palpay_secret')
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame(PaymentStatus::Paid, $order->payment->fresh()->status);
    }

    public function test_unknown_gateway_returns_404(): void
    {
        $response = $this->postJson('/api/payments/callback/stripe', [
            'payload' => ['order_id' => 1],
            'signature' => 'x',
            'reference_id' => 'x',
        ]);

        $response->assertNotFound();
        $response->assertJson(['success' => false]);
    }

    public function test_malformed_callback_returns_422(): void
    {
        $response = $this->postJson('/api/payments/callback/jawwal_pay', [
            'signature' => 'x',
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_signature_returns_422_and_keeps_payment_pending(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');
        $payment = $order->payment;

        $response = $this->postJson('/api/payments/callback/jawwal_pay', [
            'payload' => ['order_id' => $order->id],
            'signature' => 'bad-signature',
            'reference_id' => $payment->reference_id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_unknown_reference_returns_404(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');

        $payload = ['order_id' => $order->id, 'amount' => 40.00];
        $signature = hash_hmac('sha256', json_encode($payload), (string) config('payment.jawwal_secret'));

        $response = $this->postJson('/api/payments/callback/jawwal_pay', [
            'payload' => $payload,
            'signature' => $signature,
            'reference_id' => 'JW-does-not-exist',
        ]);

        $response->assertNotFound();
        $response->assertJson(['success' => false, 'message' => 'Payment not found']);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $order = $this->makeOrderWithPayment('jawwal_pay');
        $data = $this->signedPayload($order, 'payment.jawwal_secret');

        $this->postJson('/api/payments/callback/jawwal_pay', $data)->assertOk();

        $response = $this->postJson('/api/payments/callback/jawwal_pay', $data);

        $response->assertOk();
        $response->assertJson(['success' => true, 'message' => 'Already processed']);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }
}
