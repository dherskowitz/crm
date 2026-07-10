import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Inbox,
    Pin,
    PinOff,
    SearchX,
} from 'lucide-react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import type { CSSProperties } from 'react';
import BulkLeadController from '@/actions/App/Http/Controllers/BulkLeadController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import SavedViewController from '@/actions/App/Http/Controllers/SavedViewController';
import InputError from '@/components/input-error';
import { KanbanBoard } from '@/components/leads/kanban-board';
import { StageBadge } from '@/components/leads/stage-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatLeadValue } from '@/lib/leads';
import { cn } from '@/lib/utils';
import {
    exportMethod,
    importMethod,
    index as leadsIndex,
    show,
} from '@/routes/leads';
import type { LeadSummary, Option, TagItem } from '@/types';

type Filters = {
    stage?: string;
    unassigned?: boolean;
    mine?: boolean;
    search?: string;
    tag_id?: number;
};

type SavedView = { id: number; name: string; filters: Filters };

type ColumnId =
    'select' | 'name' | 'company' | 'stage' | 'owner' | 'value' | 'actions';

/**
 * List-view columns with fixed widths so frozen (sticky-left) columns can be
 * pinned at deterministic pixel offsets. A trailing flexible spacer cell
 * absorbs slack, so these widths hold even when the table is wider than its
 * content (which is what keeps the offsets correct).
 */
const LIST_COLUMNS: {
    id: ColumnId;
    label: string;
    width: number;
    align?: 'right';
    freezable?: boolean;
}[] = [
    { id: 'select', label: '', width: 44 },
    { id: 'name', label: 'Name', width: 220, freezable: true },
    { id: 'company', label: 'Company', width: 190, freezable: true },
    { id: 'stage', label: 'Stage', width: 130, freezable: true },
    { id: 'owner', label: 'Owner', width: 170, freezable: true },
    {
        id: 'value',
        label: 'Value',
        width: 130,
        align: 'right',
        freezable: true,
    },
    { id: 'actions', label: '', width: 110, align: 'right' },
];

/** Cumulative left offset (px) of each column, used to pin frozen columns. */
const COLUMN_OFFSETS = LIST_COLUMNS.map((_, index) =>
    LIST_COLUMNS.slice(0, index).reduce((sum, col) => sum + col.width, 0),
);

// Column freezing is a per-browser UI preference. It is kept in localStorage
// and read through useSyncExternalStore so server render and client hydration
// agree on the default (no setState-in-effect, no hydration mismatch).
const FREEZE_KEY = 'leads.list.frozen-up-to';
const FREEZE_EVENT = 'leads:freeze-change';
const FREEZE_DEFAULT = 1; // freeze the select + Name columns by default.

function readFrozenUpTo(): number {
    const raw = window.localStorage.getItem(FREEZE_KEY);

    if (raw === null) {
        return FREEZE_DEFAULT;
    }

    const value = Number(raw);

    return Number.isInteger(value) ? value : FREEZE_DEFAULT;
}

function subscribeFrozenUpTo(onChange: () => void): () => void {
    window.addEventListener(FREEZE_EVENT, onChange);
    window.addEventListener('storage', onChange);

    return () => {
        window.removeEventListener(FREEZE_EVENT, onChange);
        window.removeEventListener('storage', onChange);
    };
}

function writeFrozenUpTo(value: number): void {
    window.localStorage.setItem(FREEZE_KEY, String(value));
    window.dispatchEvent(new Event(FREEZE_EVENT));
}

type Props = {
    leads: LeadSummary[];
    stages: Option[];
    owners: Option[];
    tags: TagItem[];
    savedViews: SavedView[];
    filters: Filters;
};

export default function LeadsIndex({
    leads,
    stages,
    owners,
    tags,
    savedViews,
    filters,
}: Props) {
    const { auth } = usePage().props;
    const [view, setView] = useState<'board' | 'list'>('board');
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next: Partial<Filters>) => {
        router.get(
            leadsIndex().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        filters.stage ||
        filters.unassigned ||
        filters.mine ||
        filters.search ||
        filters.tag_id,
    );

    const clearFilters = () => {
        setSearch('');
        router.get(
            leadsIndex().url,
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const form = useForm<{
        name: string;
        email: string;
        phone: string;
        confirmed: boolean;
    }>({ name: '', email: '', phone: '', confirmed: false });

    const submitCreate = (confirmed = false) => {
        form.transform((data) => ({ ...data, confirmed }));
        form.post(LeadController.store().url, {
            onSuccess: () => {
                form.reset();
                setCreateOpen(false);
            },
        });
    };

    const claim = (lead: LeadSummary) => {
        router.post(LeadController.claim(lead.id).url, undefined, {
            preserveScroll: true,
        });
    };

    const saveCurrentView = () => {
        const name = window.prompt('Name this view');

        if (!name) {
            return;
        }

        router.post(
            SavedViewController.store().url,
            { name, filters },
            { preserveScroll: true },
        );
    };

    const applyView = (view: SavedView) => {
        router.get(leadsIndex().url, { ...view.filters }, { replace: true });
    };

    const [selected, setSelected] = useState<number[]>([]);
    const toggleSelect = (id: number) =>
        setSelected((s) =>
            s.includes(id) ? s.filter((x) => x !== id) : [...s, id],
        );
    const allSelected = leads.length > 0 && selected.length === leads.length;

    const bulk = (action: string, params: Record<string, unknown> = {}) => {
        if (selected.length === 0) {
            return;
        }

        router.post(
            BulkLeadController.update().url,
            { ids: selected, action, ...params },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    };

    // List view paginates client-side over the full result set the server
    // already sends (the board needs every lead), so paging never refetches.
    // `page` is derived-clamped against the current result size so a shrinking
    // filter can never strand us on an out-of-range page.
    const PAGE_SIZE = 50;
    const [pageRaw, setPage] = useState(1);
    const pageCount = Math.max(1, Math.ceil(leads.length / PAGE_SIZE));
    const page = Math.min(pageRaw, pageCount);
    const pageStart = (page - 1) * PAGE_SIZE;
    const pagedLeads = leads.slice(pageStart, pageStart + PAGE_SIZE);

    // j/k list navigation — a highlighted row that Enter opens. Active only in
    // list view, and never while the user is typing in a field or menu.
    const [activeRow, setActiveRow] = useState<number>(-1);
    const rowRefs = useRef<(HTMLTableRowElement | null)[]>([]);

    const goToPage = (next: number) => {
        setPage(next);
        setActiveRow(-1);
    };

    // Frozen (sticky-left) columns: indices 0..frozenUpTo stay pinned while the
    // rest of the table scrolls horizontally. -1 freezes nothing.
    const frozenUpTo = useSyncExternalStore(
        subscribeFrozenUpTo,
        readFrozenUpTo,
        () => FREEZE_DEFAULT,
    );

    const columnCellProps = (
        index: number,
        kind: 'head' | 'body',
    ): { className: string; style: CSSProperties } => {
        const column = LIST_COLUMNS[index];
        const isFrozen = index <= frozenUpTo;
        const isBoundary = index === frozenUpTo;
        const z =
            kind === 'head'
                ? isFrozen
                    ? 'z-30'
                    : 'z-20'
                : isFrozen
                  ? 'z-10'
                  : '';

        return {
            className: cn(
                kind === 'head' && 'sticky top-0 bg-muted',
                isFrozen && 'sticky',
                isFrozen && kind === 'body' && 'bg-inherit',
                z,
                isBoundary && 'shadow-[inset_-1px_0_0_0_var(--color-border)]',
                column.align === 'right' && 'text-right',
            ),
            style: {
                width: column.width,
                minWidth: column.width,
                ...(isFrozen ? { left: COLUMN_OFFSETS[index] } : {}),
            },
        };
    };

    const renderCell = (id: ColumnId, lead: LeadSummary) => {
        switch (id) {
            case 'select':
                return (
                    <Checkbox
                        checked={selected.includes(lead.id)}
                        onCheckedChange={() => toggleSelect(lead.id)}
                        aria-label={`Select ${lead.name}`}
                    />
                );
            case 'name':
                return (
                    <Link
                        href={show(lead.id).url}
                        className="font-medium underline-offset-4 outline-none group-hover:text-primary-emphasis hover:underline focus-visible:underline"
                    >
                        {lead.name}
                    </Link>
                );
            case 'company':
                return (
                    <span className="text-muted-foreground">
                        {lead.company?.name ?? '—'}
                    </span>
                );
            case 'stage':
                return <StageBadge stage={lead.stage} />;
            case 'owner':
                return lead.owner?.name ? (
                    <span className="text-muted-foreground">
                        {lead.owner.name}
                    </span>
                ) : (
                    <span className="text-muted-foreground italic">
                        Unassigned
                    </span>
                );
            case 'value':
                return (
                    <span className="tabular-nums">
                        {formatLeadValue(lead.value)}
                    </span>
                );
            case 'actions':
                return lead.owner ? null : (
                    <Button
                        size="sm"
                        variant="outline"
                        className="h-7 px-2.5 opacity-0 transition-opacity duration-150 ease-[var(--ease-out)] group-hover:opacity-100 focus-visible:opacity-100"
                        onClick={() => claim(lead)}
                    >
                        Claim
                    </Button>
                );
        }
    };

    const switchView = (next: 'board' | 'list') => {
        setView(next);

        if (next !== 'list') {
            setActiveRow(-1);
        }
    };

    useEffect(() => {
        if (view !== 'list') {
            return;
        }

        const handler = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                target &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.tagName === 'SELECT' ||
                    target.isContentEditable ||
                    target.closest('[role="dialog"]') ||
                    target.closest('[role="menu"]') ||
                    target.closest('[role="listbox"]'))
            ) {
                return;
            }

            if (pagedLeads.length === 0) {
                return;
            }

            if (event.key === 'j' || event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveRow((current) => {
                    const next = Math.min(current + 1, pagedLeads.length - 1);
                    rowRefs.current[next]?.scrollIntoView({
                        block: 'nearest',
                    });

                    return next;
                });
            } else if (event.key === 'k' || event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveRow((current) => {
                    const next = Math.max(current - 1, 0);
                    rowRefs.current[next]?.scrollIntoView({
                        block: 'nearest',
                    });

                    return next;
                });
            } else if (event.key === 'Enter' && activeRow >= 0) {
                const lead = pagedLeads[activeRow];

                if (lead) {
                    event.preventDefault();
                    router.visit(show(lead.id).url);
                }
            }
        };

        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, [view, pagedLeads, activeRow]);

    return (
        <div className="flex h-[calc(100svh-4.5rem)] w-full flex-col overflow-hidden px-4 py-6 group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100svh-4rem)]">
            <Head title="Leads" />

            <div className="mb-6 flex shrink-0 flex-wrap items-center justify-between gap-4 border-b border-border pb-4">
                <div className="space-y-0.5">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Leads
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Work the pipeline from the shared queue to a close
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {auth.can.manage_users && (
                        <Button variant="outline" asChild>
                            <Link href={importMethod()}>Import CSV</Link>
                        </Button>
                    )}
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button data-test="new-lead-button">
                                New lead
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create a lead</DialogTitle>
                                <DialogDescription>
                                    Add a lead manually. Inbound leads arrive
                                    automatically from the website.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        autoFocus
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                {form.errors.email ? (
                                    <Button
                                        variant="destructive"
                                        onClick={() => submitCreate(true)}
                                        disabled={form.processing}
                                    >
                                        Create anyway
                                    </Button>
                                ) : (
                                    <Button
                                        onClick={() => submitCreate(false)}
                                        disabled={form.processing}
                                    >
                                        {form.processing && <Spinner />}
                                        Create lead
                                    </Button>
                                )}
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <div className="mb-4 flex shrink-0 flex-wrap items-center gap-2">
                <Input
                    placeholder="Search name or email…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) =>
                        e.key === 'Enter' && applyFilters({ search })
                    }
                    className="max-w-xs"
                />
                <Select
                    value={filters.stage ?? 'all'}
                    onValueChange={(value) =>
                        applyFilters({
                            stage: value === 'all' ? undefined : value,
                        })
                    }
                >
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="All stages" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All stages</SelectItem>
                        {stages.map((s) => (
                            <SelectItem key={s.value} value={s.value}>
                                {s.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select
                    value={filters.tag_id ? String(filters.tag_id) : 'all'}
                    onValueChange={(value) =>
                        applyFilters({
                            tag_id: value === 'all' ? undefined : Number(value),
                        })
                    }
                >
                    <SelectTrigger className="w-40">
                        <SelectValue placeholder="All tags" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All tags</SelectItem>
                        {tags.map((t) => (
                            <SelectItem key={t.id} value={String(t.id)}>
                                {t.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    variant={filters.unassigned ? 'default' : 'outline'}
                    size="sm"
                    onClick={() =>
                        applyFilters({ unassigned: !filters.unassigned })
                    }
                    title="Show only unclaimed leads in the shared queue"
                >
                    Unassigned
                </Button>

                {savedViews.length > 0 && (
                    <Select
                        onValueChange={(id) => {
                            const view = savedViews.find(
                                (v) => String(v.id) === id,
                            );

                            if (view) {
                                applyView(view);
                            }
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Saved views" />
                        </SelectTrigger>
                        <SelectContent>
                            {savedViews.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    {v.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <Button variant="outline" size="sm" onClick={saveCurrentView}>
                    Save view
                </Button>
                {auth.can.export && (
                    <Button variant="outline" size="sm" asChild>
                        <a href={exportMethod({ query: filters }).url}>
                            Export CSV
                        </a>
                    </Button>
                )}

                <div className="ml-auto inline-flex items-center rounded-md border border-border bg-card p-0.5">
                    <button
                        type="button"
                        onClick={() => switchView('board')}
                        className={cn(
                            'rounded-[5px] px-3 py-1 text-sm font-medium transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            view === 'board'
                                ? 'bg-secondary text-secondary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                        aria-pressed={view === 'board'}
                    >
                        Board
                    </button>
                    <button
                        type="button"
                        onClick={() => switchView('list')}
                        className={cn(
                            'rounded-[5px] px-3 py-1 text-sm font-medium transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            view === 'list'
                                ? 'bg-secondary text-secondary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                        aria-pressed={view === 'list'}
                    >
                        List
                    </button>
                </div>
            </div>

            {view === 'list' && selected.length > 0 && (
                <div className="mb-3 flex shrink-0 flex-wrap items-center gap-2 rounded-lg border border-border bg-accent/40 px-3 py-2 text-sm">
                    <span className="font-medium tabular-nums">
                        {selected.length} selected
                    </span>
                    <span className="mr-1 h-4 w-px bg-border" aria-hidden />
                    <button
                        type="button"
                        onClick={() => setSelected([])}
                        className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Clear
                    </button>
                    <Select onValueChange={(v) => bulk('stage', { stage: v })}>
                        <SelectTrigger className="h-8 w-36">
                            <SelectValue placeholder="Set stage" />
                        </SelectTrigger>
                        <SelectContent>
                            {stages
                                .filter(
                                    (s) =>
                                        s.value !== 'won' && s.value !== 'lost',
                                )
                                .map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <Select
                        onValueChange={(v) =>
                            bulk('tag', { tag_id: Number(v) })
                        }
                    >
                        <SelectTrigger className="h-8 w-36">
                            <SelectValue placeholder="Add tag" />
                        </SelectTrigger>
                        <SelectContent>
                            {tags.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    {t.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {auth.can.reassign_leads && (
                        <Select
                            onValueChange={(v) =>
                                bulk('reassign', {
                                    owner_id: v === 'none' ? null : Number(v),
                                })
                            }
                        >
                            <SelectTrigger className="h-8 w-36">
                                <SelectValue placeholder="Reassign" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Unassign</SelectItem>
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
                    {auth.can.delete_leads && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => bulk('delete')}
                        >
                            Delete
                        </Button>
                    )}
                </div>
            )}

            {view === 'board' ? (
                <div className="min-h-0 flex-1">
                    <KanbanBoard leads={leads} stages={stages} />
                </div>
            ) : (
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-border bg-card">
                    <div className="min-h-0 flex-1 overflow-auto">
                        <table className="w-full table-fixed border-collapse text-sm">
                            <thead>
                                <tr className="text-left text-xs tracking-wide text-muted-foreground">
                                    {LIST_COLUMNS.map((column, index) => {
                                        const cell = columnCellProps(
                                            index,
                                            'head',
                                        );
                                        const isFrozen = index <= frozenUpTo;

                                        return (
                                            <th
                                                key={column.id}
                                                className={cn(
                                                    'h-9 border-b border-border px-3 align-middle font-medium',
                                                    column.id === 'select' &&
                                                        'px-4',
                                                    cell.className,
                                                )}
                                                style={cell.style}
                                            >
                                                {column.id === 'select' ? (
                                                    <Checkbox
                                                        checked={allSelected}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setSelected(
                                                                checked
                                                                    ? leads.map(
                                                                          (l) =>
                                                                              l.id,
                                                                      )
                                                                    : [],
                                                            )
                                                        }
                                                        aria-label="Select all leads"
                                                    />
                                                ) : column.freezable ? (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <button
                                                                type="button"
                                                                className="-mx-1 inline-flex items-center gap-1 rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                                            >
                                                                {column.label}
                                                                {isFrozen ? (
                                                                    <Pin className="size-3 text-primary-emphasis" />
                                                                ) : (
                                                                    <ChevronDown className="size-3 opacity-50" />
                                                                )}
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="start">
                                                            {isFrozen ? (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        writeFrozenUpTo(
                                                                            index -
                                                                                1,
                                                                        )
                                                                    }
                                                                >
                                                                    <PinOff className="size-4" />
                                                                    Unfreeze
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() =>
                                                                        writeFrozenUpTo(
                                                                            index,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pin className="size-4" />
                                                                    Freeze up to
                                                                    here
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                ) : (
                                                    column.label
                                                )}
                                            </th>
                                        );
                                    })}
                                    <th
                                        aria-hidden
                                        className="sticky top-0 z-20 h-9 border-b border-border bg-muted"
                                    />
                                </tr>
                            </thead>
                            <tbody>
                                {pagedLeads.map((lead, rowIndex) => (
                                    <tr
                                        key={lead.id}
                                        ref={(element) => {
                                            rowRefs.current[rowIndex] = element;
                                        }}
                                        onMouseEnter={() =>
                                            setActiveRow(rowIndex)
                                        }
                                        className={cn(
                                            'group h-11 bg-card transition-colors duration-150 ease-[var(--ease-out)]',
                                            activeRow === rowIndex
                                                ? 'bg-accent ring-1 ring-ring/40 ring-inset'
                                                : 'hover:bg-accent/50',
                                        )}
                                    >
                                        {LIST_COLUMNS.map((column, index) => {
                                            const cell = columnCellProps(
                                                index,
                                                'body',
                                            );

                                            return (
                                                <td
                                                    key={column.id}
                                                    className={cn(
                                                        'truncate border-b border-border/60 px-3 group-last:border-0',
                                                        column.id ===
                                                            'select' && 'px-4',
                                                        cell.className,
                                                    )}
                                                    style={cell.style}
                                                >
                                                    {renderCell(
                                                        column.id,
                                                        lead,
                                                    )}
                                                </td>
                                            );
                                        })}
                                        <td
                                            aria-hidden
                                            className="border-b border-border/60 group-last:border-0"
                                        />
                                    </tr>
                                ))}
                                {leads.length === 0 && (
                                    <tr>
                                        <td colSpan={LIST_COLUMNS.length + 1}>
                                            {hasActiveFilters ? (
                                                <EmptyState
                                                    icon={SearchX}
                                                    title="No leads match these filters"
                                                    description="Try widening your search or clearing a filter to see more of the queue."
                                                    action={
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={
                                                                clearFilters
                                                            }
                                                        >
                                                            Clear filters
                                                        </Button>
                                                    }
                                                />
                                            ) : (
                                                <EmptyState
                                                    icon={Inbox}
                                                    title="The queue is empty"
                                                    description="Inbound leads arrive here automatically from your website. You can also add one by hand to get started."
                                                    action={
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setCreateOpen(
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            New lead
                                                        </Button>
                                                    }
                                                />
                                            )}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {leads.length > 0 && (
                        <div className="flex shrink-0 items-center justify-between gap-3 border-t border-border bg-card px-3 py-2 text-xs text-muted-foreground">
                            <span className="tabular-nums">
                                {pageStart + 1}–
                                {Math.min(pageStart + PAGE_SIZE, leads.length)}{' '}
                                of {leads.length}
                            </span>
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 px-2"
                                    disabled={page <= 1}
                                    onClick={() =>
                                        goToPage(Math.max(1, page - 1))
                                    }
                                >
                                    <ChevronLeft className="size-4" />
                                    Prev
                                </Button>
                                <span className="px-1 tabular-nums">
                                    Page {page} of {pageCount}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 px-2"
                                    disabled={page >= pageCount}
                                    onClick={() =>
                                        goToPage(Math.min(pageCount, page + 1))
                                    }
                                >
                                    Next
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

LeadsIndex.layout = () => ({
    breadcrumbs: [{ title: 'Leads', href: leadsIndex() }],
});
