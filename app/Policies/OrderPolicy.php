<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() || $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * An order deleted by mistake comes back the same way it went: by an administrator.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
