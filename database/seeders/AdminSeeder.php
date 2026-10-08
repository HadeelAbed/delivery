<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (User::where('role', UserRole::Admin->value)->exists()) {
            return;
        }

        User::create([
            'name' => 'Platform Admin',
            'email' => (string) env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => (string) env('ADMIN_PASSWORD', 'password'),
            'phone' => '0000000000',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Active->value,
        ]);
    }
}
