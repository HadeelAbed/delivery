<?php

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_deactivate_own_account(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->post('/account/deactivate');

        $response->assertRedirect('/');
        $this->assertSame(UserStatus::Deactivated, $user->fresh()->status);
        $this->assertGuest();
    }

    public function test_deactivation_writes_audit_log(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/account/deactivate');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'deactivate_account',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_deactivation_revokes_api_tokens(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->createToken('mobile');

        $this->actingAs($user)->post('/account/deactivate');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'deactivated']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_still_log_in(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('customer.home'));
        $this->assertAuthenticated();
    }

    public function test_deactivation_requires_authentication(): void
    {
        $response = $this->post('/account/deactivate');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivation_does_not_affect_other_users(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/account/deactivate');

        $this->assertSame(UserStatus::Active, $other->fresh()->status);
    }

    public function test_deactivated_user_profile_page_shows_deactivation_form(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/customer/profile');

        $response->assertOk();
        $response->assertSee(route('account.deactivate'), false);
    }
}
