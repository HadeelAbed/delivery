<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_merchant_is_blocked_from_dashboard(): void
    {
        $merchant = User::factory()->merchant()->create();
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
        ]);

        $response = $this->actingAs($merchant)->get(route('merchant.dashboard'));

        $response->assertStatus(403);
        $response->assertSee('Pending Approval');
    }

    public function test_admin_approval_unlocks_merchant(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = User::factory()->merchant()->create();
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.approve', $merchant));

        $response->assertRedirect();
        $this->assertSame(UserStatus::Active, $merchant->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approve_merchant',
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $merchant->id,
            'notifiable_type' => User::class,
        ]);

        $dashboard = $this->actingAs($merchant->fresh())->get(route('merchant.dashboard'));
        $dashboard->assertStatus(200);
    }

    public function test_rejection_stores_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = User::factory()->merchant()->create();
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza',
        ]);

        $this->actingAs($admin)->post(route('admin.users.reject', $merchant), [
            'reason' => 'Missing documents',
        ]);

        $this->assertSame(UserStatus::Rejected, $merchant->fresh()->status);
        $this->assertSame('Missing documents', $merchant->merchantProfile->fresh()->rejection_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reject_merchant',
            'reason' => 'Missing documents',
        ]);
    }

    public function test_pending_driver_is_blocked_from_dashboard(): void
    {
        $driver = User::factory()->driver()->create();

        $response = $this->actingAs($driver)->get(route('driver.dashboard'));

        $response->assertStatus(403);
        $response->assertSee('Pending Approval');
    }
}
