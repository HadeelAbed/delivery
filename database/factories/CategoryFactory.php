<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'is_active' => true,
        ];
    }
}
