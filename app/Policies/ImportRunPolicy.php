<?php

namespace App\Policies;

use App\Models\ImportRun;
use App\Models\User;

/**
 * Runs are written by the importer: they are read, never created or edited by hand.
 */
class ImportRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ImportRun $run): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportRun $run): bool
    {
        return false;
    }

    public function delete(User $user, ImportRun $run): bool
    {
        return $user->isAdmin();
    }
}
