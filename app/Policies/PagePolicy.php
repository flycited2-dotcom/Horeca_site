<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Страницы сайта (ТЗ §12) ведёт администратор: их тексты — обещания магазина покупателю.
 * Обязательные страницы из §5.5 не удаляются — на них ссылаются подвал, формы и заявка на опт.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Page $page): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->isAdmin() && ! $page->isRequired();
    }
}
