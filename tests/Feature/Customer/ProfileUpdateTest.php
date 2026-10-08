<?php

namespace Tests\Feature\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_form_posts_with_csrf_token_and_put_spoofing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/customer/profile');

        $response->assertOk();
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="_method"', false);
        $response->assertSee('value="PUT"', false);
        $response->assertSee('<form action="/customer/profile" method="POST"', false);
    }

    public function test_customer_can_update_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/customer/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => '0599999999',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('0599999999', $user->fresh()->phone);
    }

    public function test_profile_update_rejects_email_taken_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->put('/customer/profile', [
            'name' => $user->name,
            'email' => $other->email,
            'phone' => $user->phone,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $response = $this->put('/customer/profile', [
            'name' => 'X',
            'email' => 'x@example.com',
        ]);

        $response->assertRedirect('/login');
    }
}
