import {
    DndContext,
    DragOverlay,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { ReasonDialog } from '@/components/leads/reason-dialog';
import { formatLeadValue, patchLeadStage } from '@/lib/leads';
import { cn } from '@/lib/utils';
import { show } from '@/routes/leads';
import type { LeadSummary, Option, Stage } from '@/types';

/**
 * The visual card. Rendered both inside a column (as the draggable child) and,
 * while dragging, inside the unclipped <DragOverlay> portal.
 */
function LeadCardBody({
    lead,
    isOverlay = false,
}: {
    lead: LeadSummary;
    isOverlay?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-md border border-border bg-card p-2.5',
                isOverlay
                    ? 'cursor-grabbing shadow-[var(--shadow-overlay)]'
                    : 'shadow-xs transition-[border-color,box-shadow] duration-150 ease-[var(--ease-out)] hover:border-ring/40',
            )}
        >
            <Link
                href={show(lead.id).url}
                className="block text-sm font-medium break-words underline-offset-4 outline-none hover:text-primary-emphasis hover:underline focus-visible:underline"
                onClick={(event) => event.stopPropagation()}
            >
                {lead.name}
            </Link>
            {lead.company && (
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {lead.company.name}
                </p>
            )}
            <div className="mt-2 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                <span className="truncate">
                    {lead.owner?.name ?? (
                        <span className="italic">Unassigned</span>
                    )}
                </span>
                {lead.value && (
                    <span className="shrink-0 font-medium text-foreground tabular-nums">
                        {formatLeadValue(lead.value)}
                    </span>
                )}
            </div>
        </div>
    );
}

function DraggableLeadCard({ lead }: { lead: LeadSummary }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: lead.id,
    });

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'cursor-grab touch-none rounded-md active:cursor-grabbing',
                isDragging && 'opacity-40',
            )}
            {...listeners}
            {...attributes}
        >
            <LeadCardBody lead={lead} />
        </div>
    );
}

function Column({ stage, leads }: { stage: Option; leads: LeadSummary[] }) {
    const { setNodeRef, isOver } = useDroppable({ id: stage.value });

    return (
        <div className="flex w-72 shrink-0 flex-col rounded-lg border border-border bg-muted/30">
            <div className="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                <span className="text-sm font-semibold tracking-tight">
                    {stage.label}
                </span>
                <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-secondary px-1.5 text-xs font-medium text-secondary-foreground tabular-nums">
                    {leads.length}
                </span>
            </div>
            <div
                ref={setNodeRef}
                className={cn(
                    'flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto rounded-b-lg border-t border-border p-2 transition-colors duration-150 ease-[var(--ease-out)]',
                    isOver && 'bg-primary/5 ring-2 ring-ring ring-inset',
                )}
            >
                {leads.length === 0 ? (
                    <p className="px-1 py-6 text-center text-xs text-muted-foreground">
                        No leads
                    </p>
                ) : (
                    leads.map((lead) => (
                        <DraggableLeadCard key={lead.id} lead={lead} />
                    ))
                )}
            </div>
        </div>
    );
}

export function KanbanBoard({
    leads,
    stages,
}: {
    leads: LeadSummary[];
    stages: Option[];
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    );
    const [activeId, setActiveId] = useState<number | null>(null);
    const [pendingTerminal, setPendingTerminal] = useState<{
        lead: LeadSummary;
        stage: 'won' | 'lost';
    } | null>(null);

    const activeLead =
        activeId !== null
            ? (leads.find((l) => l.id === activeId) ?? null)
            : null;

    const onDragStart = (event: DragStartEvent) => {
        setActiveId(event.active.id as number);
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActiveId(null);

        const overStage = event.over?.id as Stage | undefined;
        const lead = leads.find((l) => l.id === event.active.id);

        if (!lead || !overStage || lead.stage === overStage) {
            return;
        }

        if (overStage === 'won' || overStage === 'lost') {
            setPendingTerminal({ lead, stage: overStage });

            return;
        }

        patchLeadStage(lead.id, lead.name, overStage);
    };

    return (
        <>
            <DndContext
                id="leads-kanban"
                sensors={sensors}
                onDragStart={onDragStart}
                onDragEnd={onDragEnd}
                onDragCancel={() => setActiveId(null)}
            >
                <div className="flex h-full gap-4 overflow-x-auto pb-4">
                    {stages.map((stage) => (
                        <Column
                            key={stage.value}
                            stage={stage}
                            leads={leads.filter((l) => l.stage === stage.value)}
                        />
                    ))}
                </div>

                <DragOverlay>
                    {activeLead ? (
                        <LeadCardBody lead={activeLead} isOverlay />
                    ) : null}
                </DragOverlay>
            </DndContext>

            <ReasonDialog
                stage={pendingTerminal?.stage ?? null}
                onConfirm={(key, reason) => {
                    if (!pendingTerminal) {
                        return;
                    }

                    patchLeadStage(
                        pendingTerminal.lead.id,
                        pendingTerminal.lead.name,
                        pendingTerminal.stage,
                        { [key]: reason },
                    );
                    setPendingTerminal(null);
                }}
                onOpenChange={(open) => !open && setPendingTerminal(null)}
            />
        </>
    );
}
