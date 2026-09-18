<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * Suppliers and everything around the import are staff-only; deleting is an
 * administrator's decision, because it takes the whole catalog with it (TZ §12).
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }
}
