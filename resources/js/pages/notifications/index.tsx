import { Head, Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { show } from '@/routes/leads';
import { index as notificationsIndex } from '@/routes/notifications';
import type { NotificationItem } from '@/types';

type Props = { notifications: NotificationItem[] };

export default function NotificationsIndex({ notifications }: Props) {
    const reducedMotion = useReducedMotion();

    const markRead = (id: string) => {
        router.post(
            NotificationController.markRead().url,
            { id },
            { preserveScroll: true },
        );
    };

    const hasUnread = notifications.some((item) => !item.read_at);

    return (
        <div className="px-4 py-6">
            <Head title="Notifications" />
            <div className="mb-6 flex items-center justify-between">
                <Heading
                    title="Notifications"
                    description="Your recent activity"
                />
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!hasUnread}
                    onClick={() =>
                        router.post(
                            NotificationController.markRead().url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Mark all read
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-border bg-card">
                {notifications.length === 0 ? (
                    <EmptyState
                        icon={Bell}
                        title="You're all caught up"
                        description="Assignments, mentions, and lead updates land here. We'll let you know the moment something needs your attention."
                    />
                ) : (
                    notifications.map((item, index) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-4 border-b border-border/60 px-4 py-3 transition-colors duration-150 ease-[var(--ease-out)] last:border-0 hover:bg-accent/50 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1"
                            style={
                                reducedMotion
                                    ? undefined
                                    : {
                                          animationDelay: `${Math.min(index, 8) * 35}ms`,
                                          animationDuration: '220ms',
                                          animationFillMode: 'backwards',
                                      }
                            }
                        >
                            <div className="flex items-center gap-3">
                                <span
                                    className={
                                        item.read_at
                                            ? 'size-2 shrink-0 rounded-full bg-transparent'
                                            : 'size-2 shrink-0 rounded-full bg-primary'
                                    }
                                    aria-hidden
                                />
                                {item.data.lead_id ? (
                                    <Link
                                        href={show(item.data.lead_id)}
                                        className="text-sm underline-offset-4 outline-none hover:text-primary-emphasis hover:underline focus-visible:underline"
                                        onClick={() => markRead(item.id)}
                                    >
                                        {item.data.message}
                                    </Link>
                                ) : (
                                    <span className="text-sm">
                                        {item.data.message}
                                    </span>
                                )}
                            </div>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {new Date(item.created_at).toLocaleString()}
                            </span>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

NotificationsIndex.layout = () => ({
    breadcrumbs: [{ title: 'Notifications', href: notificationsIndex() }],
});
