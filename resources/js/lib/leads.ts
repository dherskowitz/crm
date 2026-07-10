import { router } from '@inertiajs/react';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import type { Option } from '@/types';

export const WIN_REASONS: Option[] = [
    { value: 'best_fit', label: 'Best Fit' },
    { value: 'price', label: 'Price' },
    { value: 'relationship', label: 'Relationship' },
    { value: 'timing', label: 'Timing' },
    { value: 'other', label: 'Other' },
];

export const LOST_REASONS: Option[] = [
    { value: 'not_interested', label: 'Not Interested' },
    { value: 'no_budget', label: 'No Budget' },
    { value: 'lost_to_competitor', label: 'Lost to Competitor' },
    { value: 'no_response', label: 'No Response' },
    { value: 'other', label: 'Other' },
];

/**
 * Format a lead value as a dollar amount, or an em dash when empty.
 */
export function formatLeadValue(value: string | null): string {
    return value ? '$' + Number(value).toLocaleString() : '—';
}

/**
 * Patch a lead's stage (and any terminal reason) via the update endpoint.
 * `onSuccess` lets the lead detail modal refresh itself after the change;
 * `preserveState` keeps the current page (board/list/modal) mounted.
 */
export function patchLeadStage(
    leadId: number,
    leadName: string,
    stage: string,
    extra: object = {},
    options: { onSuccess?: () => void } = {},
): void {
    router.patch(
        LeadController.update(leadId).url,
        { name: leadName, stage, ...extra },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: options.onSuccess,
        },
    );
}
