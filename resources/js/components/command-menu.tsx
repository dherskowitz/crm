import { router, usePage } from '@inertiajs/react';
import {
    Copy,
    LayoutGrid,
    LifeBuoy,
    Monitor,
    Moon,
    Plus,
    ScrollText,
    Search,
    Sun,
    Target,
    Upload,
    Users,
    Zap,
} from 'lucide-react';
import * as React from 'react';

import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard, help } from '@/routes';
import { index as auditIndex } from '@/routes/audit';
import { index as automationsIndex } from '@/routes/automations';
import { index as duplicatesIndex } from '@/routes/duplicates';
import { importMethod, index as leadsIndex } from '@/routes/leads';
import { index as usersIndex } from '@/routes/users';

type CommandMenuProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CommandMenu({ open, onOpenChange }: CommandMenuProps) {
    const { auth } = usePage().props;
    const { updateAppearance } = useAppearance();
    const [query, setQuery] = React.useState('');

    const handleOpenChange = React.useCallback(
        (next: boolean) => {
            if (!next) {
                setQuery('');
            }

            onOpenChange(next);
        },
        [onOpenChange],
    );

    const runCommand = React.useCallback(
        (action: () => void) => {
            handleOpenChange(false);
            action();
        },
        [handleOpenChange],
    );

    const navigate = React.useCallback(
        (url: string) => runCommand(() => router.visit(url)),
        [runCommand],
    );

    const trimmedQuery = query.trim();

    return (
        <CommandDialog open={open} onOpenChange={handleOpenChange}>
            <CommandInput
                placeholder="Search or jump to…"
                value={query}
                onValueChange={setQuery}
            />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>

                <CommandGroup heading="Actions">
                    {trimmedQuery.length > 0 && (
                        <CommandItem
                            value={`search leads ${trimmedQuery}`}
                            onSelect={() =>
                                runCommand(() =>
                                    router.get(leadsIndex().url, {
                                        search: trimmedQuery,
                                    }),
                                )
                            }
                        >
                            <Search />
                            <span>Search leads for “{trimmedQuery}”</span>
                        </CommandItem>
                    )}
                    <CommandItem
                        value="new lead create"
                        onSelect={() => navigate(leadsIndex().url)}
                    >
                        <Plus />
                        <span>New lead</span>
                    </CommandItem>
                </CommandGroup>

                <CommandSeparator />

                <CommandGroup heading="Navigation">
                    <CommandItem
                        value="dashboard home"
                        onSelect={() => navigate(dashboard().url)}
                    >
                        <LayoutGrid />
                        <span>Dashboard</span>
                    </CommandItem>
                    <CommandItem
                        value="leads"
                        onSelect={() => navigate(leadsIndex().url)}
                    >
                        <Target />
                        <span>Leads</span>
                    </CommandItem>
                    {auth.can.merge_duplicates && (
                        <CommandItem
                            value="duplicates merge"
                            onSelect={() => navigate(duplicatesIndex().url)}
                        >
                            <Copy />
                            <span>Duplicates</span>
                        </CommandItem>
                    )}
                    {auth.can.manage_users && (
                        <>
                            <CommandItem
                                value="automations"
                                onSelect={() =>
                                    navigate(automationsIndex().url)
                                }
                            >
                                <Zap />
                                <span>Automations</span>
                            </CommandItem>
                            <CommandItem
                                value="users team"
                                onSelect={() => navigate(usersIndex().url)}
                            >
                                <Users />
                                <span>Users</span>
                            </CommandItem>
                            <CommandItem
                                value="import leads csv"
                                onSelect={() => navigate(importMethod().url)}
                            >
                                <Upload />
                                <span>Import</span>
                            </CommandItem>
                        </>
                    )}
                    {auth.can.view_audit_log && (
                        <CommandItem
                            value="audit log history"
                            onSelect={() => navigate(auditIndex().url)}
                        >
                            <ScrollText />
                            <span>Audit log</span>
                        </CommandItem>
                    )}
                    <CommandItem
                        value="help support"
                        onSelect={() => navigate(help().url)}
                    >
                        <LifeBuoy />
                        <span>Help</span>
                    </CommandItem>
                </CommandGroup>

                <CommandSeparator />

                <CommandGroup heading="Appearance">
                    <CommandItem
                        value="appearance light theme"
                        onSelect={() =>
                            runCommand(() => updateAppearance('light'))
                        }
                    >
                        <Sun />
                        <span>Light</span>
                    </CommandItem>
                    <CommandItem
                        value="appearance dark theme"
                        onSelect={() =>
                            runCommand(() => updateAppearance('dark'))
                        }
                    >
                        <Moon />
                        <span>Dark</span>
                    </CommandItem>
                    <CommandItem
                        value="appearance system theme"
                        onSelect={() =>
                            runCommand(() => updateAppearance('system'))
                        }
                    >
                        <Monitor />
                        <span>System</span>
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}
