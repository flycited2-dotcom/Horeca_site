<?php

namespace App\Policies;

use App\Models\ProductCollection;
use App\Models\User;

/**
 * Подборки ведут менеджеры, как товары и разделы (ТЗ §2.1); удаляет — администратор.
 */
class ProductCollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ProductCollection $collection): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ProductCollection $collection): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ProductCollection $collection): bool
    {
        return $user->isAdmin();
    }
}
