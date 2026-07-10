import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { PieLabelRenderProps } from 'recharts';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDueDate, setTaskCompleted } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showLead } from '@/routes/leads';
import { index as tasksIndex } from '@/routes/tasks';
import type { TaskRow } from '@/types';

type Breakdown = { key: string; label: string; count: number };

type Props = {
    range: { from: string; to: string };
    conversion: { total: number; won: number; rate: number };
    funnel: {
        stages: { stage: string; label: string; count: number }[];
        avgDaysToConvert: number | null;
    };
    wonValue: number;
    sources: Breakdown[];
    campaigns: Breakdown[];
    winReasons: Breakdown[];
    lostReasons: Breakdown[];
    speedToLead: { avgHours: number | null; contacted: number };
    coldLeads: {
        id: number;
        name: string;
        owner: string | null;
        last_activity_at: string | null;
    }[];
    myTasks: TaskRow[];
    leaderboard?: {
        owner: string;
        total: number;
        won: number;
        rate: number;
        value: number;
    }[];
};

/**
 * Brand-anchored chart ramp, sourced from the theme tokens so series stay
 * on-brand and adapt to light/dark automatically.
 */
const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const AXIS_TICK = {
    fill: 'var(--muted-foreground)',
    fontSize: 11,
} as const;

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatPercent(rate: number): string {
    return `${(rate * 100).toFixed(1)}%`;
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    // Fixed locale + timezone so SSR and client render identical text
    // (a locale/timezone-dependent date is a classic hydration mismatch).
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(iso));
}

/**
 * Popover-styled tooltip: hairline border, overlay shadow, tabular figures —
 * matches the dropdown/popover surface rather than Recharts' default chrome.
 */
type TooltipPayloadEntry = {
    name?: string | number;
    value?: string | number;
    payload?: { label?: string };
};

function ChartTooltip({
    active,
    payload,
    valueFormatter,
}: {
    active?: boolean;
    payload?: TooltipPayloadEntry[];
    valueFormatter?: (value: number) => string;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const entry = payload[0];
    const label = entry.payload?.label ?? entry.name;
    const raw =
        typeof entry.value === 'number'
            ? entry.value
            : Number(entry.value ?? 0);

    return (
        <div
            className="rounded-md border border-border bg-popover px-2.5 py-1.5 text-popover-foreground"
            style={{ boxShadow: 'var(--shadow-overlay)' }}
        >
            <p className="text-xs font-medium">{String(label ?? '')}</p>
            <p className="text-sm font-semibold tabular-nums">
                {valueFormatter ? valueFormatter(raw) : raw.toLocaleString()}
            </p>
        </div>
    );
}

/**
 * A reusable framed surface. Panels — not cards — carry the dashboard:
 * hairline border, a labelled header row, divider, and a content area.
 */
function Panel({
    title,
    aside,
    children,
    className,
}: {
    title: string;
    aside?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-lg border border-border bg-card ${className ?? ''}`}
        >
            <header className="flex items-center justify-between gap-3 px-4 py-3">
                <h3 className="text-sm font-semibold tracking-tight">
                    {title}
                </h3>
                {aside}
            </header>
            <div className="border-t border-border px-4 py-4">{children}</div>
        </section>
    );
}

function EmptyNote({ children }: { children: ReactNode }) {
    return (
        <p className="py-6 text-center text-sm text-muted-foreground">
            {children}
        </p>
    );
}

/**
 * The current user's open tasks, surfaced for one-click acknowledgement.
 * Checking a task completes it and the panel refreshes from the server.
 */
function MyTasksPanel({ tasks }: { tasks: TaskRow[] }) {
    return (
        <Panel
            title="My tasks"
            aside={
                <Link
                    href={tasksIndex()}
                    className="text-xs font-medium text-primary-emphasis underline-offset-4 hover:underline"
                >
                    View all
                </Link>
            }
        >
            {tasks.length === 0 ? (
                <EmptyNote>No open tasks — you're all clear.</EmptyNote>
            ) : (
                <ul className="-my-1 divide-y divide-border/60">
                    {tasks.map((task) => (
                        <li
                            key={task.id}
                            className="flex items-start gap-3 py-2.5"
                        >
                            <Checkbox
                                checked={false}
                                onCheckedChange={() =>
                                    setTaskCompleted(task.id, true)
                                }
                                className="mt-0.5"
                                aria-label="Mark task complete"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm">{task.title}</p>
                                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
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
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

/**
 * Dense metric block: a label/value pair separated by hairline dividers in a
 * single panel. Deliberately not the big-number-hero or icon-card-grid cliché.
 */
function MetricRow({
    metrics,
}: {
    metrics: { label: string; value: string; hint?: string }[];
}) {
    return (
        <dl className="grid grid-cols-2 divide-x divide-y divide-border overflow-hidden rounded-lg border border-border bg-card sm:grid-cols-4 sm:divide-y-0">
            {metrics.map((metric) => (
                <div key={metric.label} className="px-4 py-3.5">
                    <dt className="text-xs font-medium tracking-wide text-muted-foreground">
                        {metric.label}
                    </dt>
                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                        {metric.value}
                    </dd>
                    {metric.hint && (
                        <dd className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                            {metric.hint}
                        </dd>
                    )}
                </div>
            ))}
        </dl>
    );
}

function BreakdownPie({ title, data }: { title: string; data: Breakdown[] }) {
    return (
        <Panel title={title}>
            {data.length === 0 ? (
                <EmptyNote>No data yet.</EmptyNote>
            ) : (
                <div className="overflow-x-auto">
                    <ResponsiveContainer width="100%" height={232}>
                        <PieChart>
                            <Pie
                                data={data}
                                dataKey="count"
                                nameKey="label"
                                cx="50%"
                                cy="50%"
                                innerRadius={52}
                                outerRadius={84}
                                paddingAngle={2}
                                stroke="var(--card)"
                                strokeWidth={2}
                                label={(props: PieLabelRenderProps) =>
                                    String(props.name ?? '')
                                }
                                labelLine={false}
                                style={{
                                    fontSize: 11,
                                    fill: 'var(--muted-foreground)',
                                }}
                            >
                                {data.map((entry, index) => (
                                    <Cell
                                        key={entry.key}
                                        fill={
                                            CHART_COLORS[
                                                index % CHART_COLORS.length
                                            ]
                                        }
                                    />
                                ))}
                            </Pie>
                            <Tooltip
                                cursor={false}
                                content={<ChartTooltip />}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
            )}
        </Panel>
    );
}

function BreakdownBars({ title, data }: { title: string; data: Breakdown[] }) {
    return (
        <Panel title={title}>
            {data.length === 0 ? (
                <EmptyNote>No data yet.</EmptyNote>
            ) : (
                <div className="overflow-x-auto">
                    <ResponsiveContainer width="100%" height={232}>
                        <BarChart
                            data={data}
                            margin={{ top: 4, right: 4, bottom: 0, left: -16 }}
                        >
                            <XAxis
                                dataKey="label"
                                tick={AXIS_TICK}
                                tickLine={false}
                                axisLine={{ stroke: 'var(--border)' }}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={AXIS_TICK}
                                tickLine={false}
                                axisLine={false}
                                width={40}
                            />
                            <Tooltip
                                cursor={{ fill: 'var(--accent)', opacity: 0.5 }}
                                content={<ChartTooltip />}
                            />
                            <Bar
                                dataKey="count"
                                fill="var(--chart-1)"
                                radius={[3, 3, 0, 0]}
                                maxBarSize={48}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </Panel>
    );
}

export default function Dashboard({
    range,
    conversion,
    funnel,
    wonValue,
    sources,
    campaigns,
    winReasons,
    lostReasons,
    speedToLead,
    coldLeads,
    leaderboard,
    myTasks,
}: Props) {
    const { auth } = usePage().props;
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);

    const applyRange = (nextFrom: string, nextTo: string) => {
        router.get(
            dashboard().url,
            { from: nextFrom, to: nextTo },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-col gap-6 px-4 py-6" data-tabular>
            <Head title="Dashboard" />

            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-4">
                <div className="space-y-0.5">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pipeline performance and reporting
                    </p>
                </div>

                <div className="flex items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            max={to}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                applyRange(event.target.value, to);
                            }}
                            className="w-40"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            min={from}
                            onChange={(event) => {
                                setTo(event.target.value);
                                applyRange(from, event.target.value);
                            }}
                            className="w-40"
                        />
                    </div>
                </div>
            </div>

            <MetricRow
                metrics={[
                    {
                        label: 'Conversion rate',
                        value: formatPercent(conversion.rate),
                        hint: `${conversion.won} of ${conversion.total} won`,
                    },
                    {
                        label: 'Won deals',
                        value: `${conversion.won}`,
                        hint: `of ${conversion.total} total`,
                    },
                    {
                        label: 'Won value',
                        value: formatCurrency(wonValue),
                    },
                    {
                        label: 'Avg. speed to lead',
                        value:
                            speedToLead.avgHours === null
                                ? '—'
                                : `${speedToLead.avgHours}h`,
                        hint: `${speedToLead.contacted} contacted`,
                    },
                ]}
            />

            <MyTasksPanel tasks={myTasks} />

            <Panel
                title="Stage funnel"
                aside={
                    funnel.avgDaysToConvert !== null ? (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            avg. {funnel.avgDaysToConvert} days to convert
                        </span>
                    ) : undefined
                }
            >
                <div className="overflow-x-auto">
                    <ResponsiveContainer width="100%" height={280}>
                        <BarChart
                            data={funnel.stages}
                            margin={{ top: 4, right: 4, bottom: 0, left: -16 }}
                        >
                            <XAxis
                                dataKey="label"
                                tick={AXIS_TICK}
                                tickLine={false}
                                axisLine={{ stroke: 'var(--border)' }}
                            />
                            <YAxis
                                allowDecimals={false}
                                tick={AXIS_TICK}
                                tickLine={false}
                                axisLine={false}
                                width={40}
                            />
                            <Tooltip
                                cursor={{ fill: 'var(--accent)', opacity: 0.5 }}
                                content={<ChartTooltip />}
                            />
                            <Bar
                                dataKey="count"
                                radius={[3, 3, 0, 0]}
                                maxBarSize={64}
                            >
                                {funnel.stages.map((entry, index) => (
                                    <Cell
                                        key={entry.stage}
                                        fill={
                                            CHART_COLORS[
                                                index % CHART_COLORS.length
                                            ]
                                        }
                                    />
                                ))}
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </Panel>

            <div className="grid gap-4 lg:grid-cols-2">
                <BreakdownBars title="Source attribution" data={sources} />
                <BreakdownBars title="Campaign attribution" data={campaigns} />
                <BreakdownPie title="Win reasons" data={winReasons} />
                <BreakdownPie title="Lost reasons" data={lostReasons} />
            </div>

            {auth.can.team_reporting && leaderboard && (
                <Panel title="Leaderboard" className="overflow-hidden">
                    {leaderboard.length === 0 ? (
                        <EmptyNote>No owned leads in this range.</EmptyNote>
                    ) : (
                        <div className="-mx-4 -my-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs font-medium tracking-wide text-muted-foreground">
                                        <th className="px-4 py-2.5">Owner</th>
                                        <th className="px-4 py-2.5 text-right">
                                            Leads
                                        </th>
                                        <th className="px-4 py-2.5 text-right">
                                            Won
                                        </th>
                                        <th className="px-4 py-2.5 text-right">
                                            Conversion
                                        </th>
                                        <th className="px-4 py-2.5 text-right">
                                            Won value
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {leaderboard.map((row) => (
                                        <tr
                                            key={row.owner}
                                            className="border-b border-border/60 last:border-0 hover:bg-accent/40"
                                        >
                                            <td className="px-4 py-2.5 font-medium">
                                                {row.owner}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {row.total}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {row.won}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {formatPercent(row.rate)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right font-medium tabular-nums">
                                                {formatCurrency(row.value)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            )}

            <Panel title="Cold leads">
                {coldLeads.length === 0 ? (
                    <EmptyNote>No cold leads — nice work.</EmptyNote>
                ) : (
                    <ul className="-my-2 divide-y divide-border/60">
                        {coldLeads.map((lead) => (
                            <li
                                key={lead.id}
                                className="flex items-center justify-between gap-4 py-2.5"
                            >
                                <Link
                                    href={showLead(lead.id).url}
                                    className="font-medium text-foreground underline-offset-4 hover:text-primary-emphasis hover:underline"
                                >
                                    {lead.name}
                                </Link>
                                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                    <span>{lead.owner ?? 'Unassigned'}</span>
                                    <span className="tabular-nums">
                                        {formatDate(lead.last_activity_at)}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
});
