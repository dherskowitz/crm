import { router } from '@inertiajs/react';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import type { TaskGroup } from '@/types';

/**
 * Mark a task complete or reopen it via the shared update endpoint. The server
 * re-renders the current page (back()), so the task moves between buckets and
 * the dashboard count refreshes without a manual reload.
 */
export function setTaskCompleted(taskId: number, completed: boolean): void {
    router.patch(
        TaskController.update(taskId).url,
        { completed },
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Human label and ordering for each task bucket. Order drives section sequence
 * on the Tasks screen.
 */
export const TASK_GROUPS: { key: TaskGroup; label: string }[] = [
    { key: 'overdue', label: 'Overdue' },
    { key: 'today', label: 'Due today' },
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'no_due_date', label: 'No due date' },
    { key: 'completed', label: 'Completed' },
];

/**
 * Format a task's due date deterministically (fixed locale + UTC) so SSR and
 * the browser render identical text — a locale/timezone date is a hydration
 * mismatch.
 */
export function formatDueDate(iso: string | null): string {
    if (!iso) {
        return 'No due date';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(iso));
}
