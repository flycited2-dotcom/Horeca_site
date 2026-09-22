<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Leads are the managers' work (TZ §12): staff see and change them, only an administrator
 * deletes one.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }
}
