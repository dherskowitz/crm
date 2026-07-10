import { Head } from '@inertiajs/react';
import {
    BellRing,
    Copy,
    FileUp,
    Filter,
    Inbox,
    ListTodo,
    MessageSquare,
    Search,
    Shield,
    Target,
    TrendingUp,
    Workflow,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { cn } from '@/lib/utils';
import { help } from '@/routes';

type Topic = {
    id: string;
    icon: LucideIcon;
    title: string;
    summary: string;
    points: string[];
};

const TOPICS: Topic[] = [
    {
        id: 'pipeline',
        icon: Target,
        title: 'Work the pipeline',
        summary:
            'Leads move through New → Contacted → Qualified → In Progress → Won / Lost.',
        points: [
            'On the Board view, drag a card between columns to change its stage.',
            'You can also change the stage from the selector on the lead page.',
            'Marking a lead Won or Lost asks for a reason so you can learn from outcomes.',
        ],
    },
    {
        id: 'queue',
        icon: Inbox,
        title: 'The queue & ownership',
        summary:
            'New leads arrive unassigned in the shared queue until someone takes them.',
        points: [
            'Hit Claim to take ownership — the rest of the team is notified to stop working it.',
            'Filter by "Queue" to see only unassigned leads.',
            'Managers and admins can reassign any lead, individually or in bulk.',
        ],
    },
    {
        id: 'activity',
        icon: MessageSquare,
        title: 'Log every touch',
        summary: 'Keep a complete timeline of your interactions on each lead.',
        points: [
            'Add rich-text notes; type @ to mention a teammate and notify them.',
            'Log calls and emails as typed activity entries.',
            'Pin important notes to keep them at the top of the timeline.',
            'Stage changes and website submissions are recorded automatically.',
        ],
    },
    {
        id: 'tasks',
        icon: ListTodo,
        title: 'Follow-up tasks',
        summary: 'Never let a follow-up slip through the cracks.',
        points: [
            'Add tasks with a due date from the lead page.',
            'Get a notification when a task is due or overdue.',
            'Open tasks show on each lead so the next step is always visible.',
        ],
    },
    {
        id: 'organize',
        icon: Filter,
        title: 'Tags, filters & saved views',
        summary: 'Slice the list down to exactly what you need.',
        points: [
            'Tag leads, then filter by stage, owner, tag, the queue, or a search term.',
            'Save a filter combination as a view to return to it in one click.',
        ],
    },
    {
        id: 'inbound',
        icon: Inbox,
        title: 'Inbound leads',
        summary: 'Website contact-form submissions are captured automatically.',
        points: [
            'New submissions land in the queue with their campaign attribution.',
            'A repeat submission from the same person is appended to the existing lead, not duplicated.',
        ],
    },
    {
        id: 'notifications',
        icon: BellRing,
        title: 'Notifications',
        summary: 'The bell in the top bar keeps you on top of what matters.',
        points: [
            'You are alerted about new queue leads, @mentions, claimed leads, and due tasks.',
            'Open a notification to jump straight to the lead.',
        ],
    },
    {
        id: 'import',
        icon: FileUp,
        title: 'Import leads',
        summary: 'Admins can migrate existing leads from a spreadsheet.',
        points: [
            'Use Import CSV on the Leads page: upload a file and map your columns.',
            'Duplicates against existing leads and within the file are skipped automatically.',
        ],
    },
    {
        id: 'duplicates',
        icon: Copy,
        title: 'Find & merge duplicates',
        summary:
            'Managers and admins can clean up records that represent the same person or company.',
        points: [
            'The Duplicates screen groups leads sharing an email or phone, and companies with the same name.',
            'Pick the record to keep and merge — history is preserved and the merge is reversible.',
        ],
    },
    {
        id: 'reporting',
        icon: TrendingUp,
        title: 'Reporting',
        summary:
            'The Dashboard turns your pipeline into the metrics that predict outcomes.',
        points: [
            'See conversion rate, the stage funnel, won value, attribution, win/loss reasons, and speed-to-lead.',
            'Filter every metric by date range.',
            'Managers and admins also see the team leaderboard and can export to CSV.',
        ],
    },
    {
        id: 'automations',
        icon: Workflow,
        title: 'Automations',
        summary: 'Admins can put routine follow-ups on autopilot.',
        points: [
            'Create a follow-up task automatically when a lead reaches a stage.',
            'Notify the owner when a lead goes idle for a number of days.',
        ],
    },
    {
        id: 'roles',
        icon: Shield,
        title: 'Roles & permissions',
        summary: 'What each role can do.',
        points: [
            'Members work their own leads plus the shared queue.',
            'Managers see all leads, reassign, merge, report on the team, and export.',
            'Admins also manage users, automations, imports, and the audit log, and can delete records.',
        ],
    },
];

export default function Help() {
    const [query, setQuery] = useState('');
    const [activeId, setActiveId] = useState(TOPICS[0].id);
    const reducedMotion = useReducedMotion();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) {
            return TOPICS;
        }

        return TOPICS.filter((topic) =>
            [topic.title, topic.summary, ...topic.points]
                .join(' ')
                .toLowerCase()
                .includes(q),
        );
    }, [query]);

    // Scrollspy: highlight the section nearest the top of the viewport.
    useEffect(() => {
        const sections = filtered
            .map((t) => document.getElementById(t.id))
            .filter((el): el is HTMLElement => el !== null);

        if (sections.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((e) => e.isIntersecting)
                    .sort(
                        (a, b) =>
                            a.boundingClientRect.top - b.boundingClientRect.top,
                    );

                if (visible[0]) {
                    setActiveId(visible[0].target.id);
                }
            },
            { rootMargin: '-80px 0px -70% 0px', threshold: 0 },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [filtered]);

    const jumpTo = (id: string) => {
        document.getElementById(id)?.scrollIntoView({
            behavior: reducedMotion ? 'auto' : 'smooth',
            block: 'start',
        });
        setActiveId(id);
    };

    return (
        <div className="px-4 py-6">
            <Head title="Help" />
            <Heading
                title="Help"
                description="A quick guide to working leads in the CRM"
                bordered
            />

            <div className="relative mt-4 max-w-md">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search help…"
                    className="pl-9"
                    aria-label="Search help"
                />
            </div>

            <div className="mt-8 grid gap-10 lg:grid-cols-[12rem_1fr]">
                <aside className="hidden lg:block">
                    <nav className="sticky top-6 flex flex-col gap-1 border-l border-border">
                        {filtered.map((topic) => (
                            <button
                                key={topic.id}
                                type="button"
                                onClick={() => jumpTo(topic.id)}
                                className={cn(
                                    '-ml-px rounded-r-md border-l-2 px-3 py-1.5 text-left text-sm transition-colors duration-150 ease-[var(--ease-out)] outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                    activeId === topic.id
                                        ? 'border-primary font-medium text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {topic.title}
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <span className="px-3 py-1.5 text-sm text-muted-foreground">
                                No matches
                            </span>
                        )}
                    </nav>
                </aside>

                <div className="max-w-3xl space-y-10">
                    {filtered.map((topic) => (
                        <section
                            key={topic.id}
                            id={topic.id}
                            className="scroll-mt-6"
                        >
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <topic.icon className="size-5 text-primary" />
                                {topic.title}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {topic.summary}
                            </p>
                            <ul className="mt-3 list-disc space-y-1.5 pl-5 text-sm text-muted-foreground marker:text-primary-emphasis">
                                {topic.points.map((point) => (
                                    <li key={point}>{point}</li>
                                ))}
                            </ul>
                        </section>
                    ))}

                    {filtered.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No help topics match “{query}”.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

Help.layout = () => ({
    breadcrumbs: [{ title: 'Help', href: help() }],
});
