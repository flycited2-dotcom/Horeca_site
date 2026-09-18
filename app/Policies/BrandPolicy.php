<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/**
 * Brands are cleaned up and merged by the manager (TZ §12); deleting one is an
 * administrator's decision.
 */
class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
