<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_order_creates_pending_payment(): void
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
            'payment_method' => 'cod',
        ]);

        $payment = app(PaymentService::class)->process($order);

        $this->assertSame('cod', $payment->method);
        $this->assertSame(PaymentStatus::PendingCod, $payment->status);
        $this->assertNull($payment->reference_id);
    }
}
