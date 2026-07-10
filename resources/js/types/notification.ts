export type NotificationData = {
    type: 'new_lead' | 'mention' | 'claimed' | 'task_due';
    lead_id?: number;
    task_id?: number;
    message: string;
};

export type NotificationItem = {
    id: string;
    data: NotificationData;
    read_at?: string | null;
    created_at: string;
};

export type NotificationsShared = {
    unread: number;
    items: NotificationItem[];
};
