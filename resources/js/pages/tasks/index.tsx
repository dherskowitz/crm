import { Head, Link, router } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import { formatDueDate, setTaskCompleted, TASK_GROUPS } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { show as showLead } from '@/routes/leads';
import { index as tasksIndex } from '@/routes/tasks';
import type { TaskGroup, TaskRow } from '@/types';

type Props = {
    tasks: TaskRow[];
    scope: 'mine' | 'team';
    canViewTeam: boolean;
};

function ScopeToggle({ scope }: { scope: 'mine' | 'team' }) {
    const select = (next: 'mine' | 'team') => {
        if (next === scope) {
            return;
        }

        router.get(
            tasksIndex().url,
            { scope: next },
            { preserveScroll: true, replace: true },
        );
    };

    const options: { value: 'mine' | 'team'; label: string }[] = [
        { value: 'mine', label: 'My tasks' },
        { value: 'team', label: 'Team' },
    ];

    return (
        <div className="inline-flex rounded-md border border-border bg-card p-0.5 text-sm">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => select(option.value)}
                    className={cn(
                        'rounded px-3 py-1 transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        scope === option.value
                            ? 'bg-secondary font-medium text-secondary-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    aria-pressed={scope === option.value}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

function TaskListItem({
    task,
    showOwner,
}: {
    task: TaskRow;
    showOwner: boolean;
}) {
    const completed = !!task.completed_at;

    return (
        <li className="flex items-start gap-3 px-4 py-3">
            <Checkbox
                checked={completed}
                onCheckedChange={() => setTaskCompleted(task.id, !completed)}
                className="mt-0.5"
                aria-label={completed ? 'Reopen task' : 'Mark task complete'}
            />
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'text-sm',
                        completed && 'text-muted-foreground line-through',
                    )}
                >
                    {task.title}
                </p>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                    {task.lead && (
                        <Link
                            href={showLead(task.lead.id)}
                            className="truncate font-medium text-foreground underline-offset-4 hover:text-primary-emphasis hover:underline"
                        >
                            {task.lead.name}
                        </Link>
                    )}
                    <span aria-hidden>·</span>
                    <span
                        className={cn(
                            'tabular-nums',
                            task.group === 'overdue' &&
                                'font-medium text-destructive',
                        )}
                    >
                        {formatDueDate(task.due_at)}
                    </span>
                    {showOwner && task.owner && (
                        <>
                            <span aria-hidden>·</span>
                            <span className="truncate">{task.owner.name}</span>
                        </>
                    )}
                </div>
            </div>
        </li>
    );
}

export default function TasksIndex({ tasks, scope, canViewTeam }: Props) {
    const sections = TASK_GROUPS.map((group) => ({
        ...group,
        items: tasks.filter((task) => task.group === group.key),
    })).filter((section) => section.items.length > 0);

    const openCount = tasks.filter((task) => !task.completed_at).length;

    return (
        <div className="px-4 py-6" data-tabular>
            <Head title="Tasks" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-4">
                <div className="space-y-0.5">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Tasks
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {scope === 'team'
                            ? "Your team's tasks — check them off as they're done."
                            : 'Your tasks across every lead — check them off as you go.'}
                    </p>
                </div>
                {canViewTeam && <ScopeToggle scope={scope} />}
            </div>

            {sections.length === 0 ? (
                <div className="rounded-lg border border-border bg-card">
                    <EmptyState
                        icon={ListChecks}
                        title="No tasks here"
                        description={
                            scope === 'team'
                                ? 'Nobody on the team has open tasks right now.'
                                : "You're all caught up. Tasks you add on a lead show up here."
                        }
                        variant="panel"
                    />
                </div>
            ) : (
                <div className="space-y-6">
                    {sections.map((section) => {
                        const groupKey: TaskGroup = section.key;

                        return (
                            <section
                                key={groupKey}
                                className="overflow-hidden rounded-lg border border-border bg-card"
                            >
                                <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
                                    <h2
                                        className={cn(
                                            'text-sm font-semibold tracking-tight',
                                            groupKey === 'overdue' &&
                                                'text-destructive',
                                        )}
                                    >
                                        {section.label}
                                    </h2>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {section.items.length}
                                    </span>
                                </header>
                                <ul className="divide-y divide-border/60">
                                    {section.items.map((task) => (
                                        <TaskListItem
                                            key={task.id}
                                            task={task}
                                            showOwner={scope === 'team'}
                                        />
                                    ))}
                                </ul>
                            </section>
                        );
                    })}
                </div>
            )}

            {sections.length > 0 && openCount === 0 && (
                <p className="mt-6 text-center text-sm text-muted-foreground">
                    No open tasks — nice work.
                </p>
            )}
        </div>
    );
}

TasksIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Tasks',
            href: tasksIndex(),
        },
    ],
});
