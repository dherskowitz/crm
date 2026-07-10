import { Search } from 'lucide-react';
import * as React from 'react';

import { useCommandMenu } from '@/components/command-menu-provider';
import { cn } from '@/lib/utils';

/**
 * Top-bar search affordance styled as a search field. Opens the ⌘K command
 * palette and advertises the shortcut. Collapses to an icon button on small
 * screens where a full field would crowd the bar.
 */
export function CommandSearchTrigger({ className }: { className?: string }) {
    const { openCommandMenu } = useCommandMenu();
    // Server has no `navigator`, so it must render the same value the client
    // renders during hydration ("Ctrl"); useSyncExternalStore swaps to the real
    // platform value after hydration without a mismatch warning.
    const isApplePlatform = React.useSyncExternalStore(
        () => () => {},
        () => /Mac|iPhone|iPad|iPod/.test(navigator.platform),
        () => false,
    );

    return (
        <button
            type="button"
            onClick={openCommandMenu}
            aria-label="Open command palette"
            className={cn(
                'group inline-flex h-8 items-center gap-2 rounded-md border border-input bg-background text-muted-foreground transition-colors duration-150 ease-[var(--ease-out)] outline-none',
                'hover:border-ring/40 hover:text-foreground',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                'w-8 justify-center px-0 sm:w-56 sm:justify-start sm:px-2.5',
                className,
            )}
        >
            <Search className="size-4 shrink-0" />
            <span className="hidden flex-1 text-left text-sm sm:inline">
                Search…
            </span>
            <kbd className="hidden items-center gap-0.5 rounded border border-border bg-muted px-1.5 font-sans text-[11px] font-medium text-muted-foreground tabular-nums sm:inline-flex">
                <span className="text-[13px] leading-none">
                    {isApplePlatform ? '⌘' : 'Ctrl'}
                </span>
                K
            </kbd>
        </button>
    );
}
