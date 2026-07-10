import { useEffect, useState } from 'react';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import { LeadDetailView } from '@/components/leads/lead-detail-view';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import type { LeadDetail, Option, TagItem } from '@/types';

type DetailData = {
    lead: LeadDetail;
    stages: Option[];
    owners: Option[];
    allTags: TagItem[];
    mentionables: { id: number; name: string }[];
};

/**
 * Opens the full lead detail surface in a modal. Data is fetched as JSON from
 * the `leads.detail` endpoint when a lead is selected, and re-fetched whenever
 * an edit inside the modal reports a change (via `refreshKey`).
 */
export function LeadDetailDialog({
    leadId,
    onClose,
}: {
    leadId: number | null;
    onClose: () => void;
}) {
    const [data, setData] = useState<DetailData | null>(null);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        if (leadId === null) {
            return;
        }

        const controller = new AbortController();

        fetch(LeadController.detail(leadId).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then((payload: DetailData) => setData(payload))
            .catch(() => {});

        return () => controller.abort();
    }, [leadId, refreshKey]);

    // Only treat the data as current once it matches the requested lead, so a
    // stale payload from a previously opened lead never flashes in.
    const detail = data && data.lead.id === leadId ? data : null;

    return (
        <Dialog
            open={leadId !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent
                aria-describedby={undefined}
                className="max-h-[90vh] w-[95vw] overflow-y-auto sm:max-w-6xl"
                onInteractOutside={(event) => {
                    // The tag dropdown and the stage/owner selects render their
                    // content in a portal outside this dialog, so a click on
                    // them registers as "outside". Keep the dialog open for
                    // those, and for the nested won/lost reason dialog.
                    // Note: the real clicked node is on the original DOM event,
                    // not on the synthetic event's target (which is the layer).
                    const target = event.detail.originalEvent
                        .target as HTMLElement | null;

                    if (
                        target?.closest(
                            '[data-radix-popper-content-wrapper],[data-radix-menu-content],[data-radix-select-content],[role="menu"],[role="listbox"],[role="dialog"]',
                        )
                    ) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader className="sr-only">
                    <DialogTitle>{detail?.lead.name ?? 'Lead'}</DialogTitle>
                </DialogHeader>
                {detail ? (
                    <LeadDetailView
                        {...detail}
                        variant="modal"
                        onChanged={() => setRefreshKey((key) => key + 1)}
                    />
                ) : (
                    <div className="space-y-4 py-2">
                        <Skeleton className="h-8 w-1/3" />
                        <div className="grid gap-6 lg:grid-cols-3">
                            <Skeleton className="h-64 lg:col-span-2" />
                            <Skeleton className="h-64" />
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
