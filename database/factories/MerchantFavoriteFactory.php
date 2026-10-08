<?php

namespace Database\Factories;

use App\Models\MerchantFavorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantFavorite>
 */
class MerchantFavoriteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fake()->randomDigitNotNull(),
            'merchant_id' => fake()->randomDigitNotNull(),
        ];
    }
}
