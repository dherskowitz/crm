import { Head } from '@inertiajs/react';
import { LeadDetailView } from '@/components/leads/lead-detail-view';
import { index as leadsIndex, show } from '@/routes/leads';
import type { LeadDetail, Option, TagItem } from '@/types';

type Props = {
    lead: LeadDetail;
    stages: Option[];
    owners: Option[];
    allTags: TagItem[];
    mentionables: { id: number; name: string }[];
};

export default function LeadShow(props: Props) {
    return (
        <div className="px-4 py-6">
            <Head title={props.lead.name} />
            <LeadDetailView {...props} />
        </div>
    );
}

// Inertia v3 calls a page's `layout` function with the page props directly
// (not a `{ props }` wrapper), so read `lead` off the argument.
LeadShow.layout = (props: { lead?: LeadDetail }) => ({
    breadcrumbs: [
        { title: 'Leads', href: leadsIndex() },
        {
            title: props.lead?.name ?? 'Lead',
            href: show(props.lead?.id ?? 0),
        },
    ],
});
