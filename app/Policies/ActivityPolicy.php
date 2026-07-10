<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function view(User $user, Activity $activity): bool
    {
        return $activity->lead->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Users may edit and delete their own activities; admins may manage any.
     * Write access never outlives read access — the lead must still be visible.
     */
    public function update(User $user, Activity $activity): bool
    {
        return $activity->lead->isVisibleTo($user)
            && ($user->isAdmin() || $activity->user_id === $user->id);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
