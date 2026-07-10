import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show } from '@/routes/leads';
import { index as notificationsIndex } from '@/routes/notifications';

export function NotificationBell() {
    const { notifications } = usePage().props;
    const { unread, items } = notifications;

    const markAllRead = () => {
        router.post(
            NotificationController.markRead().url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="Notifications"
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive-emphasis px-1 text-[10px] font-medium text-destructive-foreground tabular-nums">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notifications</span>
                    {unread > 0 && (
                        <button
                            onClick={markAllRead}
                            className="text-xs font-normal text-muted-foreground hover:underline"
                        >
                            Mark all read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {items.length === 0 ? (
                    <div className="px-2 py-6 text-center text-sm text-muted-foreground">
                        You're all caught up.
                    </div>
                ) : (
                    items.map((item) => (
                        <DropdownMenuItem key={item.id} asChild>
                            <Link
                                href={
                                    item.data.lead_id
                                        ? show(item.data.lead_id)
                                        : notificationsIndex()
                                }
                                className="flex flex-col items-start gap-0.5"
                            >
                                <span className="text-sm">
                                    {item.data.message}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href={notificationsIndex()}
                        className="justify-center text-sm"
                    >
                        View all
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
