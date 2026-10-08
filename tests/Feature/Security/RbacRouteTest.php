<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_area(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.approvals'));

        $response->assertStatus(403);
    }

    public function test_merchant_cannot_access_driver_area(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);

        $response = $this->actingAs($merchant)->get(route('driver.dashboard'));

        $response->assertStatus(403);
    }

    public function test_driver_cannot_access_merchant_area(): void
    {
        $driver = User::factory()->driver()->create(['status' => 'active']);

        $response = $this->actingAs($driver)->get(route('merchant.dashboard'));

        $response->assertStatus(403);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
                ->assertStatus(302);
        }

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertFalse(Hash::check('wrong-password', $user->password));
    }
}
