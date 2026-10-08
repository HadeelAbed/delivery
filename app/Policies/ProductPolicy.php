<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function manage(User $user, Product $product): bool
    {
        return $user->id === $product->category->merchant->user_id;
    }
}
