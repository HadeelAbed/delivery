<?php

namespace Tests\Feature\Security;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Auth\DeactivateController;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deactivation_anonymizes_personal_fields(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $originalRememberToken = $user->remember_token;

        $this->actingAs($user)->post('/account/deactivate');

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::Deactivated, $fresh->status);
        $this->assertStringStartsWith('deleted-', $fresh->name);
        $this->assertStringStartsWith('deleted-', $fresh->email);
        $this->assertStringStartsWith('deleted-', $fresh->phone);
        $this->assertSame('deleted-'.$fresh->id.'@deleted.example', $fresh->email);
        $this->assertSame('deleted-'.$fresh->id, $fresh->phone);
        // Auth::logout() cycles the remember token to a fresh random value;
        // it must no longer equal the original token (remember-me replay is dead).
        $this->assertNotSame($originalRememberToken, $fresh->remember_token);
        $this->assertNotEmpty($fresh->remember_token);
    }

    public function test_account_becomes_deactivated(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/account/deactivate');

        $this->assertSame(UserStatus::Deactivated, $user->fresh()->status);
    }

    public function test_sanctum_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->createToken('mobile');

        $this->actingAs($user)->post('/account/deactivate');

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_current_session_is_invalidated(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post('/account/deactivate');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/account/deactivate');

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_still_log_in(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('customer.home'));
        $this->assertAuthenticated();
    }

    public function test_orders_remain_intact_after_anonymization(): void
    {
        $merchant = User::factory()->create(['role' => UserRole::Merchant->value, 'status' => 'active']);
        $customer = User::factory()->create(['role' => UserRole::Customer->value, 'status' => 'active']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => OrderStatus::Pending->value,
        ]);

        $this->actingAs($customer)->post('/account/deactivate');

        $order = $order->fresh();
        $this->assertNotNull($order);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($merchant->id, $order->merchant_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNotNull(Order::find($order->id));
    }

    public function test_payments_remain_intact_after_anonymization(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value, 'status' => 'active']);
        $merchant = User::factory()->create(['role' => UserRole::Merchant->value, 'status' => 'active']);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'merchant_id' => $merchant->id]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => PaymentStatus::PendingCod->value,
            'reference_id' => 'ref-test-1',
            'payload' => ['gateway' => 'test'],
        ]);

        $this->actingAs($customer)->post('/account/deactivate');

        $freshPayment = Payment::find($payment->id);
        $this->assertNotNull($freshPayment);
        $this->assertSame('cod', $freshPayment->method);
        $this->assertSame(PaymentStatus::PendingCod, $freshPayment->status);
        $this->assertSame($order->id, $freshPayment->order_id);
    }

    public function test_deliveries_remain_intact_after_anonymization(): void
    {
        $driver = User::factory()->create(['role' => UserRole::Driver->value, 'status' => 'active']);
        $delivery = Delivery::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'assigned',
            'picked_up_at' => null,
            'delivered_at' => null,
        ]);

        $this->actingAs($driver)->post('/account/deactivate');

        $fresh = Delivery::find($delivery->id);
        $this->assertNotNull($fresh);
        $this->assertSame($driver->id, $fresh->driver_id);
        $this->assertSame('assigned', $fresh->status);
    }

    public function test_audit_record_remains_intact_and_exposes_no_personal_data(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $originalName = $user->name;
        $originalEmail = $user->email;
        $originalPhone = $user->phone;

        $this->actingAs($user)->post('/account/deactivate');

        $audit = AuditLog::where('actor_id', $user->id)
            ->where('action', 'delete_account')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(User::class, $audit->entity_type);
        $this->assertSame((string) $user->id, (string) $audit->entity_id);

        $serialized = (string) json_encode($audit->only(['action', 'entity_type', 'entity_id', 'changes', 'reason']));
        $this->assertStringNotContainsStringIgnoringCase($originalEmail, $serialized);
        $this->assertStringNotContainsStringIgnoringCase($originalName, $serialized);
        if (is_string($originalPhone) && $originalPhone !== '') {
            $this->assertStringNotContainsStringIgnoringCase($originalPhone, $serialized);
        }
    }

    public function test_anonymized_emails_and_phones_do_not_collide_across_users(): void
    {
        $first = User::factory()->create(['status' => 'active']);
        $second = User::factory()->create(['status' => 'active']);

        $this->actingAs($first)->post('/account/deactivate');
        $this->actingAs($second)->post('/account/deactivate');

        $first = $first->fresh();
        $second = $second->fresh();

        $this->assertSame(UserStatus::Deactivated, $first->status);
        $this->assertSame(UserStatus::Deactivated, $second->status);
        $this->assertNotSame($first->email, $second->email);
        $this->assertNotSame($first->phone, $second->phone);
        $this->assertSame('deleted-'.$first->id.'@deleted.example', $first->email);
        $this->assertSame('deleted-'.$second->id.'@deleted.example', $second->email);
    }

    public function test_active_unrelated_users_remain_unchanged(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $other->createToken('other-mobile');
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/account/deactivate');

        $fresh = $other->fresh();
        $this->assertSame(UserStatus::Active, $fresh->status);
        $this->assertSame($other->getRawOriginal('name'), $fresh->getRawOriginal('name'));
        $this->assertSame($other->getRawOriginal('email'), $fresh->getRawOriginal('email'));
        $this->assertSame($other->getRawOriginal('phone'), $fresh->getRawOriginal('phone'));
        $this->assertCount(1, $fresh->tokens);
    }

    public function test_transaction_rollback_keeps_everything_when_anonymization_fails(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value, 'status' => 'active']);
        $customer->createToken('rollback-test');
        $merchant = User::factory()->create(['role' => UserRole::Merchant->value, 'status' => 'active']);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'merchant_id' => $merchant->id]);

        $this->app->singleton(
            DeactivateController::class,
            fn () => new class extends DeactivateController
            {
                protected function anonymizePersonalData(User $user): void
                {
                    throw new RuntimeException('simulated anonymization failure');
                }
            }
        );

        $this->actingAs($customer);

        $failed = false;
        try {
            $this->withoutExceptionHandling()->post('/account/deactivate');
        } catch (RuntimeException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'The simulated anonymization failure should propagate.');

        $customer = $customer->fresh();
        $this->assertSame(UserStatus::Active, $customer->status);
        $this->assertCount(1, $customer->tokens);
        $this->assertCount(0, AuditLog::where('actor_id', $customer->id)->where('action', 'delete_account')->get());
        $this->assertNotNull(Order::find($order->id));
    }
}
