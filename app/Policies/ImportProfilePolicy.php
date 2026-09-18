<?php

namespace App\Policies;

use App\Models\ImportProfile;
use App\Models\User;

/**
 * A manager sees the profiles and starts them; the settings of a source are changed
 * by an administrator (TZ §12).
 */
class ImportProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ImportProfile $profile): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ImportProfile $profile): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ImportProfile $profile): bool
    {
        return $user->isAdmin();
    }

    /**
     * Starting an import by hand is an everyday task of a manager.
     */
    public function run(User $user, ImportProfile $profile): bool
    {
        return $user->isStaff();
    }
}
