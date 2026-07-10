<?php

namespace App\Support;

use App\Models\Task;

/**
 * Shapes a task for the Inertia surfaces (Tasks screen, dashboard panel) with a
 * server-computed bucket so the client never does locale/timezone date math
 * (which would diverge between SSR and the browser).
 */
class TaskPresenter
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     due_at: string|null,
     *     completed_at: string|null,
     *     group: string,
     *     lead: array{id: int, name: string}|null,
     *     owner: array{id: int, name: string}|null,
     * }
     */
    public static function present(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'due_at' => $task->due_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'group' => self::group($task),
            'lead' => $task->relationLoaded('lead') && $task->lead !== null
                ? ['id' => $task->lead->id, 'name' => $task->lead->name]
                : null,
            'owner' => $task->relationLoaded('owner') && $task->owner !== null
                ? ['id' => $task->owner->id, 'name' => $task->owner->name]
                : null,
        ];
    }

    /**
     * Bucket a task by day: completed, then no-due-date, overdue (due before
     * today), due today, or upcoming.
     */
    public static function group(Task $task): string
    {
        if ($task->completed_at !== null) {
            return 'completed';
        }

        if ($task->due_at === null) {
            return 'no_due_date';
        }

        if ($task->due_at->lt(now()->startOfDay())) {
            return 'overdue';
        }

        if ($task->due_at->lte(now()->endOfDay())) {
            return 'today';
        }

        return 'upcoming';
    }
}
