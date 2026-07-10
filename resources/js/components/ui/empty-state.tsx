import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    /** `panel` sits inside a bordered panel body; `bare` fills a table cell or open area. */
    variant?: 'panel' | 'bare';
    className?: string;
};

/**
 * A teaching empty state: a calm tinted icon, a one-line title, a short
 * explanation, and an optional primary action. Used wherever a list, table, or
 * panel has no rows — never a flat "nothing here".
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    variant = 'bare',
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center px-6 text-center duration-300 ease-[var(--ease-out)] animate-in fade-in-0',
                variant === 'panel' ? 'py-10' : 'py-16',
                className,
            )}
        >
            <span
                className="mb-4 inline-flex size-11 items-center justify-center rounded-full border border-border bg-muted/60 text-muted-foreground"
                aria-hidden
            >
                <Icon className="size-5" strokeWidth={1.75} />
            </span>
            <p className="text-sm font-semibold tracking-tight text-foreground">
                {title}
            </p>
            {description && (
                <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
