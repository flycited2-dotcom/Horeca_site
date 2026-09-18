<?php

namespace App\Policies;

use App\Models\SupplierRef;
use App\Models\User;

/**
 * Matching supplier entities to ours is the manager's daily work (TZ §12).
 * Rows appear during an import, so they are never created by hand.
 */
class SupplierRefPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, SupplierRef $ref): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SupplierRef $ref): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, SupplierRef $ref): bool
    {
        return $user->isAdmin();
    }
}
