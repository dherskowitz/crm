<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->lead->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Task owners manage their own tasks; managers and admins manage any.
     * Write access never outlives read access — the lead must still be visible.
     */
    public function update(User $user, Task $task): bool
    {
        return $task->lead->isVisibleTo($user)
            && ($user->isAdmin() || $user->isManager() || $task->owner_id === $user->id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
