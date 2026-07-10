<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $lead->isVisibleTo($user);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasCapability('delete_leads');
    }

    /**
     * Claiming requires an unassigned, visible lead.
     */
    public function claim(User $user, Lead $lead): bool
    {
        return $lead->isUnassigned() && $lead->isVisibleTo($user);
    }

    public function reassign(User $user, Lead $lead): bool
    {
        return $user->hasCapability('reassign_leads');
    }

    public function merge(User $user, Lead $lead): bool
    {
        return $user->hasCapability('merge_duplicates');
    }
}
