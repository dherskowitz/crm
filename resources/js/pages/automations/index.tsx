import { Head, router, useForm } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { useState } from 'react';
import AutomationRuleController from '@/actions/App/Http/Controllers/AutomationRuleController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index as automationsIndex } from '@/routes/automations';
import type { Option } from '@/types';

type Rule = {
    id: number;
    name: string;
    trigger: string;
    trigger_label: string;
    action: string;
    action_label: string;
    conditions: Record<string, string | number> | null;
    params: Record<string, string | number> | null;
    is_active: boolean;
};

type Props = {
    rules: Rule[];
    triggers: Option[];
    actions: Option[];
    stages: Option[];
};

type FormShape = {
    name: string;
    trigger: string;
    action: string;
    conditions: { to?: string; days?: number };
    params: { title?: string; due_in_days?: number; message?: string };
    is_active: boolean;
};

export default function AutomationsIndex({
    rules,
    triggers,
    actions,
    stages,
}: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<FormShape>({
        name: '',
        trigger: 'stage_change',
        action: 'create_task',
        conditions: {},
        params: {},
        is_active: true,
    });

    const submit = () => {
        if (editingId === null) {
            form.post(AutomationRuleController.store().url, {
                onSuccess: () => form.reset(),
            });

            return;
        }

        form.patch(AutomationRuleController.update(editingId).url, {
            onSuccess: () => {
                form.reset();
                setEditingId(null);
            },
        });
    };

    const startEdit = (rule: Rule) => {
        setEditingId(rule.id);
        form.clearErrors();
        form.setData({
            name: rule.name,
            trigger: rule.trigger,
            action: rule.action,
            conditions: (rule.conditions ?? {}) as FormShape['conditions'],
            params: (rule.params ?? {}) as FormShape['params'],
            is_active: rule.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
    };

    const toggle = (rule: Rule) => {
        router.patch(
            AutomationRuleController.update(rule.id).url,
            {
                name: rule.name,
                trigger: rule.trigger,
                action: rule.action,
                conditions: rule.conditions ?? {},
                params: rule.params ?? {},
                is_active: !rule.is_active,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="px-4 py-6">
            <Head title="Automations" />
            <Heading
                title="Automations"
                description="Predefined trigger-to-action rules for your team"
                bordered
            />

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <section className="rounded-lg border border-border bg-card">
                    <header className="flex h-11 items-center justify-between gap-3 px-4">
                        <h3 className="text-sm font-semibold tracking-tight">
                            Active rules
                        </h3>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {rules.length}{' '}
                            {rules.length === 1 ? 'rule' : 'rules'}
                        </span>
                    </header>
                    <div className="border-t border-border">
                        {rules.length === 0 ? (
                            <EmptyState
                                variant="panel"
                                icon={Zap}
                                title="No automation rules yet"
                                description="A rule watches for a trigger — like a lead going idle or reaching a stage — and runs an action automatically, such as creating a follow-up task. Build your first one on the right."
                            />
                        ) : (
                            <ul>
                                {rules.map((rule) => (
                                    <li
                                        key={rule.id}
                                        className={cn(
                                            'flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3 transition-colors duration-150 ease-[var(--ease-out)] last:border-0',
                                            editingId === rule.id
                                                ? 'bg-primary/5 ring-1 ring-ring/40 ring-inset'
                                                : 'hover:bg-accent/50',
                                        )}
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {rule.name}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {rule.trigger_label}{' '}
                                                <span aria-hidden>→</span>{' '}
                                                {rule.action_label}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <Button
                                                size="sm"
                                                className="h-7"
                                                variant={
                                                    rule.is_active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() => toggle(rule)}
                                                aria-pressed={rule.is_active}
                                            >
                                                {rule.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-7"
                                                onClick={() => startEdit(rule)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-7 text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        AutomationRuleController.destroy(
                                                            rule.id,
                                                        ).url,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => {
                                                                if (
                                                                    editingId ===
                                                                    rule.id
                                                                ) {
                                                                    cancelEdit();
                                                                }
                                                            },
                                                        },
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section className="space-y-4 self-start rounded-lg border border-border bg-card p-4">
                    <h3 className="text-sm font-semibold tracking-tight">
                        {editingId === null ? 'New rule' : 'Edit rule'}
                    </h3>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Trigger</Label>
                        <Select
                            value={form.data.trigger}
                            onValueChange={(v) =>
                                form.setData((data) => ({
                                    ...data,
                                    trigger: v,
                                    conditions: {},
                                }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {triggers.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {form.data.trigger === 'stage_change' && (
                        <div className="grid gap-2">
                            <Label>When stage becomes</Label>
                            <Select
                                value={form.data.conditions.to ?? 'any'}
                                onValueChange={(v) =>
                                    form.setData('conditions', {
                                        to: v === 'any' ? undefined : v,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Any stage" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="any">
                                        Any stage
                                    </SelectItem>
                                    {stages.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {form.data.trigger === 'lead_idle' && (
                        <div className="grid gap-2">
                            <Label>Idle for (days)</Label>
                            <Input
                                type="number"
                                min={1}
                                value={form.data.conditions.days ?? ''}
                                onChange={(e) =>
                                    form.setData('conditions', {
                                        days: Number(e.target.value),
                                    })
                                }
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label>Action</Label>
                        <Select
                            value={form.data.action}
                            onValueChange={(v) =>
                                form.setData((data) => ({
                                    ...data,
                                    action: v,
                                    params: {},
                                }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {actions.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {form.data.action === 'create_task' && (
                        <>
                            <div className="grid gap-2">
                                <Label>Task title</Label>
                                <Input
                                    value={form.data.params.title ?? ''}
                                    onChange={(e) =>
                                        form.setData('params', {
                                            ...form.data.params,
                                            title: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Due in (days)</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    value={form.data.params.due_in_days ?? ''}
                                    onChange={(e) =>
                                        form.setData('params', {
                                            ...form.data.params,
                                            due_in_days: Number(e.target.value),
                                        })
                                    }
                                />
                            </div>
                        </>
                    )}

                    {form.data.action === 'send_notification' && (
                        <div className="grid gap-2">
                            <Label>Message</Label>
                            <Input
                                value={form.data.params.message ?? ''}
                                onChange={(e) =>
                                    form.setData('params', {
                                        message: e.target.value,
                                    })
                                }
                            />
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <Button onClick={submit} disabled={form.processing}>
                            {editingId === null
                                ? 'Create rule'
                                : 'Save changes'}
                        </Button>
                        {editingId !== null && (
                            <Button
                                variant="ghost"
                                onClick={cancelEdit}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}

AutomationsIndex.layout = () => ({
    breadcrumbs: [{ title: 'Automations', href: automationsIndex() }],
});
