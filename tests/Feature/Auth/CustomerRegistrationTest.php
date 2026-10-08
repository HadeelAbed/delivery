<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_registration_creates_active_customer(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ahmed Customer',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0599123456',
            'role' => 'customer',
        ]);

        $response->assertRedirect(route('customer.home'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ahmed@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('0599123456', $user->phone);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0599123456',
            'role' => 'customer',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Bad Phone',
            'email' => 'badphone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => 'abc',
            'role' => 'customer',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_merchant_registration_starts_pending(): void
    {
        $this->post('/register', [
            'name' => 'Cafe Owner',
            'email' => 'cafe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0599123456',
            'role' => 'merchant',
        ]);

        $user = User::where('email', 'cafe@example.com')->firstOrFail();
        $this->assertSame(UserRole::Merchant, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }
}
