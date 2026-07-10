import { router } from '@inertiajs/react';
import type { JSONContent } from '@tiptap/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ActivityController from '@/actions/App/Http/Controllers/ActivityController';
import AttachmentController from '@/actions/App/Http/Controllers/AttachmentController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { AttachmentDropzone } from '@/components/attachment-dropzone';
import { ReasonDialog } from '@/components/leads/reason-dialog';
import { STAGE_LABELS, StageBadge } from '@/components/leads/stage-badge';
import { EMPTY_DOC, NoteEditor, NoteViewer } from '@/components/note-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatLeadValue, patchLeadStage } from '@/lib/leads';
import { cn } from '@/lib/utils';
import type {
    ActivityItem,
    LeadDetail,
    Option,
    TagItem,
    TaskItem,
} from '@/types';

type Props = {
    lead: LeadDetail;
    stages: Option[];
    owners: Option[];
    allTags: TagItem[];
    mentionables: { id: number; name: string }[];
    /**
     * Called after a successful mutation so a host (the detail modal) can
     * re-fetch the lead. On the full page this is omitted — the Inertia reload
     * refreshes the page props instead.
     */
    onChanged?: () => void;
    /**
     * `'modal'` caps the activity timeline so it scrolls in place instead of
     * growing the dialog; `'page'` lets it grow with the document.
     */
    variant?: 'page' | 'modal';
};

function ActivityEntry({
    activity,
    onTogglePin,
}: {
    activity: ActivityItem;
    onTogglePin: (activity: ActivityItem) => void;
}) {
    const who = activity.user?.name ?? 'System';
    const when = new Date(activity.occurred_at).toLocaleString();

    const summary = () => {
        const body = activity.body ?? {};

        switch (activity.type) {
            case 'note':
                return <NoteViewer doc={body as JSONContent} />;
            case 'call':
            case 'email':
                return <p className="text-sm">{String(body.text ?? '')}</p>;
            case 'stage_change':
                return (
                    <p className="text-sm text-muted-foreground">
                        Stage changed from{' '}
                        <strong>
                            {STAGE_LABELS[
                                body.from as keyof typeof STAGE_LABELS
                            ] ?? String(body.from)}
                        </strong>{' '}
                        to{' '}
                        <strong>
                            {STAGE_LABELS[
                                body.to as keyof typeof STAGE_LABELS
                            ] ?? String(body.to)}
                        </strong>
                    </p>
                );
            case 'form_submission':
                return (
                    <p className="text-sm text-muted-foreground">
                        Website form submission
                        {body.message ? `: ${String(body.message)}` : ''}
                    </p>
                );
            default:
                return (
                    <p className="text-sm text-muted-foreground">
                        {String(body.event ?? 'System event')}
                    </p>
                );
        }
    };

    return (
        <div
            className={cn(
                'group relative py-3.5 pl-4 before:absolute before:top-[1.4rem] before:left-0 before:h-1.5 before:w-1.5 before:-translate-y-1/2 before:rounded-full before:bg-border before:ring-4 before:ring-card',
                activity.is_pinned && 'before:bg-primary',
            )}
        >
            <div className="mb-1 flex items-center justify-between gap-3 text-xs">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    {activity.is_pinned && (
                        <span className="font-medium text-primary-emphasis">
                            Pinned
                        </span>
                    )}
                    <span className="font-medium text-foreground capitalize">
                        {activity.type.replace('_', ' ')}
                    </span>
                    <span aria-hidden>·</span>
                    <span>{who}</span>
                </span>
                <div className="flex items-center gap-2">
                    {activity.type === 'note' && (
                        <button
                            onClick={() => onTogglePin(activity)}
                            className="text-muted-foreground underline-offset-4 opacity-0 transition-opacity duration-150 ease-[var(--ease-out)] group-hover:opacity-100 hover:text-foreground hover:underline focus-visible:opacity-100 focus-visible:outline-none"
                        >
                            {activity.is_pinned ? 'Unpin' : 'Pin'}
                        </button>
                    )}
                    <span className="text-muted-foreground tabular-nums">
                        {when}
                    </span>
                </div>
            </div>
            {summary()}
        </div>
    );
}

export function LeadDetailView({
    lead,
    stages,
    owners,
    allTags,
    mentionables,
    onChanged,
    variant = 'page',
}: Props) {
    const [composer, setComposer] = useState<'note' | 'call' | 'email'>('note');
    const [noteDoc, setNoteDoc] = useState<JSONContent | null>(null);
    const [text, setText] = useState('');
    const [resetSignal, setResetSignal] = useState(0);
    const [taskTitle, setTaskTitle] = useState('');
    const [terminal, setTerminal] = useState<'won' | 'lost' | null>(null);

    // Shared Inertia visit options: keep the host page (board/list/modal)
    // mounted and let the modal refresh after the server responds.
    const visit = (extra: Record<string, unknown> = {}) => ({
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onChanged?.(),
        ...extra,
    });

    const changeStage = (stage: string, extra: object = {}) => {
        patchLeadStage(lead.id, lead.name, stage, extra, {
            onSuccess: () => onChanged?.(),
        });
    };

    const onStageSelect = (stage: string) => {
        if (stage === lead.stage) {
            return;
        }

        if (stage === 'won' || stage === 'lost') {
            setTerminal(stage);

            return;
        }

        changeStage(stage);
    };

    const logActivity = () => {
        const body = composer === 'note' ? (noteDoc ?? EMPTY_DOC) : { text };
        router.post(
            ActivityController.store(lead.id).url,
            { type: composer, body },
            visit({
                onSuccess: () => {
                    setText('');
                    setNoteDoc(null);
                    setResetSignal((n) => n + 1);
                    onChanged?.();
                },
            }),
        );
    };

    const addTask = () => {
        if (!taskTitle.trim()) {
            return;
        }

        router.post(
            TaskController.store(lead.id).url,
            { title: taskTitle },
            visit({
                onSuccess: () => {
                    setTaskTitle('');
                    onChanged?.();
                },
            }),
        );
    };

    const toggleTask = (task: TaskItem) => {
        router.patch(
            TaskController.update(task.id).url,
            { completed: !task.completed_at },
            visit(),
        );
    };

    const togglePin = (activity: ActivityItem) => {
        router.post(
            ActivityController.pin(activity.id).url,
            { pinned: !activity.is_pinned },
            visit(),
        );
    };

    const timeline = [...lead.activities].sort((a, b) => {
        if (a.is_pinned !== b.is_pinned) {
            return a.is_pinned ? -1 : 1;
        }

        return (
            new Date(b.occurred_at).getTime() -
            new Date(a.occurred_at).getTime()
        );
    });

    const toggleTag = (tagId: number) => {
        const current = lead.tags.map((t) => t.id);
        const next = current.includes(tagId)
            ? current.filter((id) => id !== tagId)
            : [...current, tagId];
        router.post(
            LeadController.syncTags(lead.id).url,
            { tag_ids: next },
            visit(),
        );
    };

    return (
        <div>
            <div
                className={cn(
                    'mb-6 flex flex-wrap items-start justify-between gap-4',
                    variant === 'page' && 'border-b border-border pb-4',
                )}
            >
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            {lead.name}
                        </h1>
                        <StageBadge stage={lead.stage} />
                    </div>
                    {lead.company && (
                        <p className="text-sm text-muted-foreground">
                            {lead.company.name}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Select value={lead.stage} onValueChange={onStageSelect}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {stages.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {owners.length > 0 && (
                        <Select
                            value={lead.owner ? String(lead.owner.id) : 'none'}
                            onValueChange={(value) =>
                                router.post(
                                    LeadController.reassign(lead.id).url,
                                    {
                                        owner_id:
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                    },
                                    visit(),
                                )
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Unassigned" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Unassigned</SelectItem>
                                {owners.map((o) => (
                                    <SelectItem
                                        key={o.value}
                                        value={String(o.value)}
                                    >
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    {!lead.owner && (
                        <Button
                            onClick={() =>
                                router.post(
                                    LeadController.claim(lead.id).url,
                                    undefined,
                                    visit(),
                                )
                            }
                        >
                            Claim
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <div className="rounded-lg border border-border bg-card p-4">
                        <div className="mb-3 inline-flex items-center gap-0.5 rounded-md border border-border bg-muted/40 p-0.5">
                            {(['note', 'call', 'email'] as const).map((t) => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => setComposer(t)}
                                    aria-pressed={composer === t}
                                    className={cn(
                                        'rounded-[5px] px-3 py-1 text-sm font-medium capitalize transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                        composer === t
                                            ? 'bg-card text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t}
                                </button>
                            ))}
                        </div>
                        {composer === 'note' ? (
                            <NoteEditor
                                onChange={setNoteDoc}
                                resetSignal={resetSignal}
                                mentionables={mentionables}
                            />
                        ) : (
                            <Input
                                value={text}
                                onChange={(e) => setText(e.target.value)}
                                placeholder={`Log a ${composer}…`}
                            />
                        )}
                        <div className="mt-2 flex justify-end">
                            <Button size="sm" onClick={logActivity}>
                                Log {composer}
                            </Button>
                        </div>
                    </div>

                    <RailPanel title="Activity">
                        {timeline.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No activity yet.
                            </p>
                        ) : (
                            <div
                                className={cn(
                                    variant === 'modal' &&
                                        'max-h-[45vh] overflow-y-auto',
                                )}
                            >
                                <div className="relative before:absolute before:top-2 before:bottom-2 before:left-[3px] before:w-px before:bg-border">
                                    <div className="divide-y divide-border/60">
                                        {timeline.map((a) => (
                                            <ActivityEntry
                                                key={a.id}
                                                activity={a}
                                                onTogglePin={togglePin}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </RailPanel>
                </div>

                <div className="space-y-4">
                    <RailPanel title="Details">
                        <dl className="divide-y divide-border/60 text-sm">
                            <Detail label="Email" value={lead.email} />
                            <Detail label="Phone" value={lead.phone} />
                            <Detail
                                label="Owner"
                                value={lead.owner?.name ?? 'Unassigned'}
                            />
                            <Detail
                                label="Value"
                                value={formatLeadValue(lead.value)}
                            />
                            <Detail label="Source" value={lead.source} />
                        </dl>
                    </RailPanel>

                    <RailPanel
                        title="Tags"
                        action={
                            <DropdownMenu modal={false}>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                    >
                                        Edit
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {allTags.map((tag) => (
                                        <DropdownMenuCheckboxItem
                                            key={tag.id}
                                            checked={lead.tags.some(
                                                (t) => t.id === tag.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleTag(tag.id)
                                            }
                                            onSelect={(e) => e.preventDefault()}
                                        >
                                            {tag.name}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        }
                    >
                        <div className="flex flex-wrap gap-1.5">
                            {lead.tags.length === 0 ? (
                                <span className="text-sm text-muted-foreground">
                                    No tags
                                </span>
                            ) : (
                                lead.tags.map((tag) => (
                                    <span
                                        key={tag.id}
                                        className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{ backgroundColor: tag.color }}
                                    >
                                        {tag.name}
                                    </span>
                                ))
                            )}
                        </div>
                    </RailPanel>

                    <RailPanel title="Tasks">
                        {lead.tasks.length > 0 && (
                            <div className="mb-3 space-y-2">
                                {lead.tasks.map((task) => (
                                    <label
                                        key={task.id}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={!!task.completed_at}
                                            onCheckedChange={() =>
                                                toggleTask(task)
                                            }
                                            className="mt-0.5"
                                        />
                                        <span
                                            className={
                                                task.completed_at
                                                    ? 'text-muted-foreground line-through'
                                                    : ''
                                            }
                                        >
                                            {task.title}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <div className="flex gap-2">
                            <Input
                                value={taskTitle}
                                onChange={(e) => setTaskTitle(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && addTask()
                                }
                                placeholder="Add a task…"
                                className="h-8"
                            />
                            <Button size="sm" onClick={addTask}>
                                Add
                            </Button>
                        </div>
                    </RailPanel>

                    <RailPanel title="Attachments">
                        {lead.attachments.length > 0 && (
                            <ul className="mb-3 divide-y divide-border/60 text-sm">
                                {lead.attachments.map((a) => (
                                    <li
                                        key={a.id}
                                        className="group flex items-center justify-between gap-2 py-1.5"
                                    >
                                        <a
                                            href={
                                                AttachmentController.download(
                                                    a.id,
                                                ).url
                                            }
                                            className="truncate underline-offset-4 hover:text-primary-emphasis hover:underline"
                                        >
                                            {a.filename}
                                        </a>
                                        <button
                                            onClick={() =>
                                                router.delete(
                                                    AttachmentController.destroy(
                                                        a.id,
                                                    ).url,
                                                    visit(),
                                                )
                                            }
                                            className="shrink-0 text-xs text-destructive underline-offset-4 opacity-0 transition-opacity duration-150 ease-[var(--ease-out)] group-hover:opacity-100 hover:underline focus-visible:opacity-100 focus-visible:outline-none"
                                        >
                                            Delete
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <AttachmentDropzone
                            leadId={lead.id}
                            onUploaded={onChanged}
                        />
                    </RailPanel>
                </div>
            </div>

            <ReasonDialog
                stage={terminal}
                onConfirm={(key, reason) => {
                    if (!terminal) {
                        return;
                    }

                    changeStage(terminal, { [key]: reason });
                    setTerminal(null);
                }}
                onOpenChange={(open) => !open && setTerminal(null)}
            />
        </div>
    );
}

/**
 * Detail-rail panel: hairline border, a labelled header row, divider, and a
 * roomier content area — calmer than the dense list surfaces.
 */
function RailPanel({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-lg border border-border bg-card">
            <header className="flex h-10 items-center justify-between gap-2 px-4">
                <h3 className="text-sm font-semibold tracking-tight">
                    {title}
                </h3>
                {action}
            </header>
            <div className="border-t border-border p-4">{children}</div>
        </section>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-1.5 first:pt-0 last:pb-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right break-all text-foreground">
                {value ?? '—'}
            </dd>
        </div>
    );
}
