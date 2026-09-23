<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

/**
 * Справочник ведут менеджеры (ТЗ §12); удаляет — администратор.
 */
class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Redirect $record): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Redirect $record): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Redirect $record): bool
    {
        return $user->isAdmin();
    }
}
