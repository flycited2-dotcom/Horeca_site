<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isStaff() || $this->belongsToCompany($user, $company);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isStaff() || $this->belongsToCompany($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }

    private function belongsToCompany(User $user, Company $company): bool
    {
        return $user->company_id !== null && $user->company_id === $company->id;
    }
}
