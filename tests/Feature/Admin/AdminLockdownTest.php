<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_create_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0599123456',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertFalse(User::where('role', UserRole::Admin->value)->exists());
    }

    public function test_admin_seeder_creates_exactly_one_admin(): void
    {
        $this->artisan('db:seed', ['--class' => AdminSeeder::class]);

        $this->assertSame(1, User::where('role', UserRole::Admin->value)->count());

        $this->artisan('db:seed', ['--class' => AdminSeeder::class]);

        $this->assertSame(1, User::where('role', UserRole::Admin->value)->count());
    }

    public function test_seeded_admin_can_access_admin_area(): void
    {
        $this->artisan('db:seed', ['--class' => AdminSeeder::class]);
        $admin = User::where('role', UserRole::Admin->value)->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.approvals'));

        $response->assertStatus(200);
    }
}
