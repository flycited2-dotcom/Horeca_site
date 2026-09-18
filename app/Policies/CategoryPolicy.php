<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * The storefront tree is the manager's work (TZ §6.6); deleting a category takes its
 * products out of the storefront structure, so only an administrator does it.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Category $category): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
