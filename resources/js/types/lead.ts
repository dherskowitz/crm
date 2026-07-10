export type Stage =
    'new' | 'contacted' | 'qualified' | 'in_progress' | 'won' | 'lost';

export type Option = { value: string; label: string };

export type OwnerRef = { id: number; name: string };
export type CompanyRef = { id: number; name: string };

export type LeadSummary = {
    id: number;
    name: string;
    email: string | null;
    stage: Stage;
    value: string | null;
    company: CompanyRef | null;
    owner: OwnerRef | null;
    tags: TagItem[];
    last_activity_at: string | null;
};

export type ActivityItem = {
    id: number;
    type:
        | 'note'
        | 'call'
        | 'email'
        | 'stage_change'
        | 'form_submission'
        | 'merge'
        | 'system';
    body: Record<string, unknown> | null;
    is_pinned: boolean;
    user: OwnerRef | null;
    occurred_at: string;
};

export type TaskItem = {
    id: number;
    title: string;
    due_at: string | null;
    completed_at: string | null;
};

export type TaskGroup =
    'overdue' | 'today' | 'upcoming' | 'no_due_date' | 'completed';

/**
 * A task as shown on the Tasks screen and dashboard panel: carries its lead,
 * owner, and a server-computed bucket so the client does no date math.
 */
export type TaskRow = {
    id: number;
    title: string;
    due_at: string | null;
    completed_at: string | null;
    group: TaskGroup;
    lead: { id: number; name: string } | null;
    owner: OwnerRef | null;
};

export type TagItem = { id: number; name: string; color: string };

export type AttachmentItem = {
    id: number;
    filename: string;
    size: number;
    mime: string;
};

export type LeadDetail = LeadSummary & {
    phone: string | null;
    source: string;
    form_type: string | null;
    lost_reason: string | null;
    win_reason: string | null;
    expected_close_date: string | null;
    first_contacted_at: string | null;
    converted_at: string | null;
    company_id: number | null;
    activities: ActivityItem[];
    tasks: TaskItem[];
    attachments: AttachmentItem[];
};
