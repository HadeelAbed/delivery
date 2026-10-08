<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Sanctum\Sanctum;

class SanctumSeeder extends Seeder
{
    public function run(): void
    {
        Sanctum::ignoreMigrations();
    }
}
