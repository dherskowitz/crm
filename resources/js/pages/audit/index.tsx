import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { index as auditIndex } from '@/routes/audit';

type AuditChanges = {
    attributes: Record<string, unknown> | null;
    old: Record<string, unknown> | null;
};

type AuditEntry = {
    id: number;
    description: string;
    log_name: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer: string | null;
    changes: AuditChanges;
    created_at: string | null;
};

type Props = {
    entries: AuditEntry[];
};

const descriptionVariant = (
    description: string,
): 'default' | 'secondary' | 'destructive' => {
    switch (description) {
        case 'created':
            return 'default';
        case 'deleted':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
};

const ChangeSummary = ({ changes }: { changes: AuditChanges }) => {
    const attributes = changes.attributes ?? {};
    const keys = Object.keys(attributes);

    if (keys.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <ul className="space-y-1">
            {keys.map((key) => (
                <li key={key} className="text-xs">
                    <span className="font-medium">{key}</span>:{' '}
                    {changes.old && key in changes.old && (
                        <>
                            <span className="text-muted-foreground line-through">
                                {formatValue(changes.old[key])}
                            </span>{' '}
                            →{' '}
                        </>
                    )}
                    <span>{formatValue(attributes[key])}</span>
                </li>
            ))}
        </ul>
    );
};

export default function AuditIndex({ entries }: Props) {
    return (
        <div className="px-4 py-6">
            <Head title="Audit log" />

            <Heading
                title="Audit log"
                description="A record of recent changes to leads, companies, users, and tasks"
                bordered
            />

            {entries.length === 0 ? (
                <div className="rounded-lg border border-border bg-card">
                    <EmptyState
                        icon={ScrollText}
                        title="No activity recorded yet"
                        description="As your team creates, edits, and merges leads, companies, and users, every change will be logged here for review."
                    />
                </div>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-border bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground">
                                <th className="px-4 py-2.5">When</th>
                                <th className="px-3 py-2.5">Event</th>
                                <th className="px-3 py-2.5">Subject</th>
                                <th className="px-3 py-2.5">By</th>
                                <th className="px-3 py-2.5">Changes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="border-b border-border/60 align-top transition-colors duration-150 ease-[var(--ease-out)] last:border-0 hover:bg-accent/50"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums">
                                        {entry.created_at
                                            ? new Date(
                                                  entry.created_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant={descriptionVariant(
                                                entry.description,
                                            )}
                                        >
                                            {entry.description}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-3 font-medium">
                                        {entry.subject_type ?? '—'}
                                        {entry.subject_id !== null && (
                                            <span className="text-muted-foreground tabular-nums">
                                                {' '}
                                                #{entry.subject_id}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-muted-foreground">
                                        {entry.causer ?? 'System'}
                                    </td>
                                    <td className="px-3 py-3">
                                        <ChangeSummary
                                            changes={entry.changes}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

AuditIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Audit log',
            href: auditIndex(),
        },
    ],
});
