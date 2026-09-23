<?php

namespace App\Policies;

use App\Models\PriceTier;
use App\Models\User;

/**
 * Ценовые группы (ТЗ §2.1, §7): менеджер их видит и назначает компаниям, создаёт, меняет
 * и удаляет только администратор — от них зависят цены всех оптовиков группы.
 */
class PriceTierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, PriceTier $tier): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PriceTier $tier): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PriceTier $tier): bool
    {
        return $user->isAdmin();
    }
}
