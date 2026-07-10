import { Head, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import MergeController from '@/actions/App/Http/Controllers/MergeController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { cn } from '@/lib/utils';
import { index as duplicatesIndex } from '@/routes/duplicates';

type DuplicateLead = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    stage: string;
    company: { id: number; name: string } | null;
    owner: { id: number; name: string } | null;
    created_at: string | null;
};

type LeadCluster = {
    key: string;
    type: 'email' | 'phone';
    value: string;
    leads: DuplicateLead[];
};

type DuplicateCompany = {
    id: number;
    name: string;
    domain: string | null;
    leads_count: number;
};

type CompanyCluster = {
    key: string;
    value: string;
    companies: DuplicateCompany[];
};

type Props = {
    leadClusters: LeadCluster[];
    companyClusters: CompanyCluster[];
};

function mergeOthersInto<T extends { id: number }>(
    members: T[],
    survivorId: number,
    url: string,
    onDone: (loserIds: number[]) => void,
): void {
    const losers = members.filter((member) => member.id !== survivorId);

    if (losers.length === 0) {
        return;
    }

    const loserIds = losers.map((loser) => loser.id);
    let remaining = loserIds.length;

    loserIds.forEach((loserId) => {
        router.post(
            url,
            { survivor_id: survivorId, loser_id: loserId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    remaining -= 1;

                    if (remaining === 0) {
                        onDone(loserIds);
                    }
                },
            },
        );
    });
}

/**
 * Reverse a just-completed merge by unmerging every loser that was folded into
 * the survivor. Used by the "Undo" action on the merge toast.
 */
function unmergeAll(loserIds: number[], url: string, onDone: () => void): void {
    if (loserIds.length === 0) {
        return;
    }

    let remaining = loserIds.length;

    loserIds.forEach((loserId) => {
        router.post(
            url,
            { loser_id: loserId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    remaining -= 1;

                    if (remaining === 0) {
                        onDone();
                    }
                },
            },
        );
    });
}

/**
 * Show a merge-success toast whose "Undo" action reverses the merge. The
 * window is generous (10s) because an accidental merge is disruptive to walk
 * back by hand.
 */
function toastMerged(
    message: string,
    loserIds: number[],
    unmergeUrl: string,
): void {
    toast.success(message, {
        duration: 10000,
        action: {
            label: 'Undo',
            onClick: () =>
                unmergeAll(loserIds, unmergeUrl, () =>
                    toast.success('Merge undone.'),
                ),
        },
    });
}

function LeadClusterCard({ cluster }: { cluster: LeadCluster }) {
    const [survivorId, setSurvivorId] = useState<number>(cluster.leads[0].id);

    const merge = () => {
        mergeOthersInto(
            cluster.leads,
            survivorId,
            MergeController.mergeLeads().url,
            (loserIds) =>
                toastMerged(
                    'Leads merged.',
                    loserIds,
                    MergeController.unmergeLeads().url,
                ),
        );
    };

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex items-center justify-between gap-4 border-b border-border bg-muted/40 px-4 py-2.5">
                <div className="flex items-center gap-2 text-sm">
                    <Badge variant="secondary">{cluster.type}</Badge>
                    <span className="font-medium">{cluster.value}</span>
                    <span className="text-muted-foreground tabular-nums">
                        {cluster.leads.length} leads
                    </span>
                </div>
                <Button size="sm" onClick={merge} data-test="merge-leads">
                    Merge into selected
                </Button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs font-medium tracking-wide text-muted-foreground">
                            <th className="w-12 px-4 py-2.5">Keep</th>
                            <th className="px-3 py-2.5">Name</th>
                            <th className="px-3 py-2.5">Email</th>
                            <th className="px-3 py-2.5">Phone</th>
                            <th className="px-3 py-2.5">Stage</th>
                            <th className="px-3 py-2.5">Owner</th>
                            <th className="px-3 py-2.5">Company</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cluster.leads.map((lead) => (
                            <tr
                                key={lead.id}
                                className={cn(
                                    'border-b border-border/60 transition-colors duration-150 ease-[var(--ease-out)] last:border-0',
                                    survivorId === lead.id
                                        ? 'bg-primary/5'
                                        : 'hover:bg-accent/50',
                                )}
                            >
                                <td className="px-4 py-2.5">
                                    <input
                                        type="radio"
                                        name={`survivor-${cluster.key}`}
                                        checked={survivorId === lead.id}
                                        onChange={() => setSurvivorId(lead.id)}
                                        aria-label={`Keep ${lead.name}`}
                                        className="size-4 accent-primary"
                                    />
                                </td>
                                <td className="px-3 py-2.5 font-medium">
                                    {lead.name}
                                </td>
                                <td className="px-3 py-2.5 text-muted-foreground">
                                    {lead.email ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-muted-foreground">
                                    {lead.phone ?? '—'}
                                </td>
                                <td className="px-3 py-2.5">{lead.stage}</td>
                                <td className="px-3 py-2.5 text-muted-foreground">
                                    {lead.owner?.name ?? 'Unassigned'}
                                </td>
                                <td className="px-3 py-2.5 text-muted-foreground">
                                    {lead.company?.name ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function CompanyClusterCard({ cluster }: { cluster: CompanyCluster }) {
    const [survivorId, setSurvivorId] = useState<number>(
        cluster.companies[0].id,
    );

    const merge = () => {
        mergeOthersInto(
            cluster.companies,
            survivorId,
            MergeController.mergeCompanies().url,
            (loserIds) =>
                toastMerged(
                    'Companies merged.',
                    loserIds,
                    MergeController.unmergeCompanies().url,
                ),
        );
    };

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex items-center justify-between gap-4 border-b border-border bg-muted/40 px-4 py-2.5">
                <div className="flex items-center gap-2 text-sm">
                    <Badge variant="secondary">company</Badge>
                    <span className="font-medium">{cluster.value}</span>
                    <span className="text-muted-foreground tabular-nums">
                        {cluster.companies.length} companies
                    </span>
                </div>
                <Button size="sm" onClick={merge} data-test="merge-companies">
                    Merge into selected
                </Button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs font-medium tracking-wide text-muted-foreground">
                            <th className="w-12 px-4 py-2.5">Keep</th>
                            <th className="px-3 py-2.5">Name</th>
                            <th className="px-3 py-2.5">Domain</th>
                            <th className="px-3 py-2.5 text-right">Leads</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cluster.companies.map((company) => (
                            <tr
                                key={company.id}
                                className={cn(
                                    'border-b border-border/60 transition-colors duration-150 ease-[var(--ease-out)] last:border-0',
                                    survivorId === company.id
                                        ? 'bg-primary/5'
                                        : 'hover:bg-accent/50',
                                )}
                            >
                                <td className="px-4 py-2.5">
                                    <input
                                        type="radio"
                                        name={`survivor-${cluster.key}`}
                                        checked={survivorId === company.id}
                                        onChange={() =>
                                            setSurvivorId(company.id)
                                        }
                                        aria-label={`Keep ${company.name}`}
                                        className="size-4 accent-primary"
                                    />
                                </td>
                                <td className="px-3 py-2.5 font-medium">
                                    {company.name}
                                </td>
                                <td className="px-3 py-2.5 text-muted-foreground">
                                    {company.domain ?? '—'}
                                </td>
                                <td className="px-3 py-2.5 text-right text-muted-foreground tabular-nums">
                                    {company.leads_count}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function DuplicatesIndex({
    leadClusters,
    companyClusters,
}: Props) {
    const isEmpty = leadClusters.length === 0 && companyClusters.length === 0;

    return (
        <div className="px-4 py-6">
            <Head title="Duplicates" />

            <Heading
                title="Potential duplicates"
                description="Review clusters of records that look like the same lead or company, then merge the duplicates into the one you keep."
                bordered
            />

            {isEmpty && (
                <div className="mt-6 rounded-lg border border-border bg-card">
                    <EmptyState
                        icon={ShieldCheck}
                        title="No duplicates found — all clean"
                        description="Every lead and company looks unique right now. When records share an email, phone, or domain, we'll surface them here so you can merge with confidence."
                    />
                </div>
            )}

            {leadClusters.length > 0 && (
                <section className="mt-6 mb-8">
                    <h2 className="mb-3 text-sm font-semibold tracking-tight">
                        Lead duplicates
                    </h2>
                    <div className="grid gap-4">
                        {leadClusters.map((cluster) => (
                            <LeadClusterCard
                                key={cluster.key}
                                cluster={cluster}
                            />
                        ))}
                    </div>
                </section>
            )}

            {companyClusters.length > 0 && (
                <section className="mt-6">
                    <h2 className="mb-3 text-sm font-semibold tracking-tight">
                        Company duplicates
                    </h2>
                    <div className="grid gap-4">
                        {companyClusters.map((cluster) => (
                            <CompanyClusterCard
                                key={cluster.key}
                                cluster={cluster}
                            />
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

DuplicatesIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Duplicates',
            href: duplicatesIndex(),
        },
    ],
});
