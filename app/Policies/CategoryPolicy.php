<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function manage(User $user, Category $category): bool
    {
        return $user->id === $category->merchant->user_id;
    }
}
