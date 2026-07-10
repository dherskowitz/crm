import * as React from 'react';

import { CommandMenu } from '@/components/command-menu';

type CommandMenuContextValue = {
    open: boolean;
    setOpen: (open: boolean) => void;
    openCommandMenu: () => void;
};

const CommandMenuContext = React.createContext<CommandMenuContextValue | null>(
    null,
);

/**
 * Provides the ⌘K command palette to the authed app shell: mounts the dialog
 * once, wires the global keyboard shortcut, and exposes an opener for the
 * top-bar search trigger.
 */
export function CommandMenuProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const [open, setOpen] = React.useState(false);

    React.useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    const value = React.useMemo<CommandMenuContextValue>(
        () => ({
            open,
            setOpen,
            openCommandMenu: () => setOpen(true),
        }),
        [open],
    );

    return (
        <CommandMenuContext.Provider value={value}>
            {children}
            <CommandMenu open={open} onOpenChange={setOpen} />
        </CommandMenuContext.Provider>
    );
}

export function useCommandMenu(): CommandMenuContextValue {
    const context = React.useContext(CommandMenuContext);

    if (!context) {
        throw new Error(
            'useCommandMenu must be used within a CommandMenuProvider.',
        );
    }

    return context;
}
