<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

/**
 * Warehouses are created by the import; the manager sets visibility, the city and the
 * delivery time to Simferopol (TZ §6.5).
 */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->isAdmin();
    }
}
