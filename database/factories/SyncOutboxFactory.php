<?php

namespace Database\Factories;

use App\Models\SyncOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncOutbox>
 */
class SyncOutboxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'type' => 'delivery',
            'payload' => [],
            'status' => 'pending',
        ];
    }
}
