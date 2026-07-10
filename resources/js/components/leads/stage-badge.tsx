import { cn } from '@/lib/utils';
import type { Stage } from '@/types';

/**
 * Tinted stage chips anchored to the brand ramp (blue → indigo → amber →
 * emerald → red). Each uses a low-alpha fill plus a contrast-safe foreground
 * so the same intent reads in light and dark. Color is paired with the label,
 * never carrying meaning alone.
 */
const STAGE_STYLES: Record<Stage, string> = {
    new: 'bg-muted text-muted-foreground ring-1 ring-inset ring-border',
    contacted:
        'bg-blue-500/10 text-blue-700 ring-1 ring-inset ring-blue-500/20 dark:bg-blue-400/15 dark:text-blue-300 dark:ring-blue-400/25',
    qualified:
        'bg-indigo-500/10 text-indigo-700 ring-1 ring-inset ring-indigo-500/20 dark:bg-indigo-400/15 dark:text-indigo-300 dark:ring-indigo-400/25',
    in_progress:
        'bg-amber-500/15 text-amber-800 ring-1 ring-inset ring-amber-500/25 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/25',
    won: 'bg-emerald-500/10 text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-400/15 dark:text-emerald-300 dark:ring-emerald-400/25',
    lost: 'bg-red-500/10 text-red-700 ring-1 ring-inset ring-red-500/20 dark:bg-red-400/15 dark:text-red-300 dark:ring-red-400/25',
};

export const STAGE_LABELS: Record<Stage, string> = {
    new: 'New',
    contacted: 'Contacted',
    qualified: 'Qualified',
    in_progress: 'In Progress',
    won: 'Won',
    lost: 'Lost',
};

export function StageBadge({ stage }: { stage: Stage }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                STAGE_STYLES[stage],
            )}
        >
            {STAGE_LABELS[stage]}
        </span>
    );
}
