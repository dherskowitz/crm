<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasCapability('merge_duplicates');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasCapability('delete_leads');
    }

    public function merge(User $user, Company $company): bool
    {
        return $user->hasCapability('merge_duplicates');
    }
}
