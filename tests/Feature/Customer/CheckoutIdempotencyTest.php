<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedMerchant(string $name = 'Test Cafe'): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => $name,
            'address' => 'Gaza, Al-Rimal',
        ]);

        return [$merchant, $profile];
    }

    private function makeProduct(int $profileId, string $price = '12.50'): Product
    {
        $category = Category::create(['merchant_id' => $profileId, 'name' => 'Pizza']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => $price,
        ]);
    }

    private function cart(int $merchantId, int $productId): array
    {
        return [
            'merchant_id' => $merchantId,
            'items' => [['product_id' => $productId, 'quantity' => 2]],
        ];
    }

    private function validAddress(): array
    {
        return ['label' => 'Gaza, Al-Rimal, Bldg 5', 'lat' => 31.5, 'lng' => 34.47];
    }

    private function postCheckout(User $customer, array $cart, string $key, ?array $address = null): TestResponse
    {
        return $this->actingAs($customer)
            ->withSession(['cart' => $cart])
            ->post(route('customer.checkout.place'), [
                'address' => $address ?? $this->validAddress(),
                'payment_method' => 'cod',
                'idempotency_key' => $key,
            ]);
    }

    public function test_repeated_submission_with_same_token_creates_single_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);
        $key = (string) Str::uuid();

        $this->postCheckout($customer, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');

        // A duplicate submission (double-click / network retry) carrying the same token
        // must not create a second order.
        $this->postCheckout($customer, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');

        $this->assertSame(1, Order::where('customer_id', $customer->id)->count());
        // No orphaned payment from the rejected duplicate insert.
        $this->assertSame(1, Payment::where('method', 'cod')->count());
    }

    public function test_duplicate_submission_returns_the_same_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);
        $key = (string) Str::uuid();

        $this->postCheckout($customer, $cart, $key)->assertRedirect();
        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        // Both submissions resolve to the exact same order (no new record created).
        $this->postCheckout($customer, $cart, $key)
            ->assertRedirect(route('customer.orders.show', $order));

        $this->assertSame(1, Order::where('customer_id', $customer->id)->count());
    }

    public function test_new_token_allows_a_legitimate_new_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);

        $this->postCheckout($customer, $cart, (string) Str::uuid())
            ->assertSessionHas('status', 'Order placed successfully.');

        // A brand-new checkout operation with a fresh token must create a new order,
        // and the cart must be cleared after each successful placement.
        $this->postCheckout($customer, $cart, (string) Str::uuid())
            ->assertSessionHas('status', 'Order placed successfully.');

        $this->assertSame(2, Order::where('customer_id', $customer->id)->count());
    }

    public function test_stale_form_after_success_creates_no_second_order(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);
        $key = (string) Str::uuid();

        // First checkout succeeds; the controller clears the session cart and forgets
        // the idempotency token.
        $this->postCheckout($customer, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');
        $this->assertSame(1, Order::where('customer_id', $customer->id)->count());

        // The customer resubmits the stale form (same old token) but the server-side
        // cart was cleared by the successful checkout. This must NOT create a second
        // order; the empty-cart guard redirects to browse instead.
        $this->postCheckout($customer, ['merchant_id' => null, 'items' => []], $key)
            ->assertRedirect(route('customer.browse'));
        $this->assertSame(1, Order::where('customer_id', $customer->id)->count());

        // A genuinely new checkout (fresh cart + fresh token) still creates a new order.
        $this->postCheckout($customer, $cart, (string) Str::uuid())
            ->assertSessionHas('status', 'Order placed successfully.');
        $this->assertSame(2, Order::where('customer_id', $customer->id)->count());
    }


    public function test_validation_failure_does_not_consume_token_and_retry_succeeds(): void
    {
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);
        $key = (string) Str::uuid();

        // Invalid address (missing lat/lng) fails validation before any order is created.
        $this->postCheckout($customer, $cart, $key, ['label' => 'Gaza'])
            ->assertSessionHasErrors('address.lat');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());

        // The idempotency key is not consumed by the failed attempt: retrying with the
        // same token and a valid address succeeds and creates exactly one order.
        $this->postCheckout($customer, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');

        $this->assertSame(1, Order::where('customer_id', $customer->id)->count());
    }

    public function test_same_key_across_two_customers_creates_separate_owned_orders(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $product = $this->makeProduct($profile->id);
        $cart = $this->cart($merchant->id, $product->id);
        $key = (string) Str::uuid();

        $this->postCheckout($customerA, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');
        $orderA = Order::where('customer_id', $customerA->id)->firstOrFail();

        // Customer B reuses the same key string. The unique index is
        // (customer_id, idempotency_key), so B must get their own order — never A's.
        $this->postCheckout($customerB, $cart, $key)
            ->assertSessionHas('status', 'Order placed successfully.');
        $orderB = Order::where('customer_id', $customerB->id)->firstOrFail();

        $this->assertNotSame($orderA->id, $orderB->id);
        $this->assertSame($customerB->id, $orderB->customer_id);
        $this->assertSame(2, Order::count());
    }

    public function test_database_enforces_unique_idempotency_key_per_customer(): void
    {
        // This is the deterministic proof of the concurrency guard that protects against
        // simultaneous duplicate submissions: the composite unique index rejects a second
        // row for the same (customer_id, idempotency_key) pair. True in-process concurrency
        // is not reproducible on the in-memory SQLite test connection, so we assert the
        // database-level invariant directly.
        $customer = User::factory()->create();
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $key = (string) Str::uuid();

        $base = [
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'items_total' => 12.50,
            'delivery_fee' => 15.00,
            'total' => 27.50,
            'delivery_address' => $this->validAddress(),
            'payment_method' => 'cod',
            'idempotency_key' => $key,
        ];

        Order::create($base);

        $this->expectException(UniqueConstraintViolationException::class);
        Order::create($base); // same customer + same key => DB rejects the duplicate
    }
}
