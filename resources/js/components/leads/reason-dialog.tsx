import { useState } from 'react';
import { STAGE_LABELS } from '@/components/leads/stage-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { LOST_REASONS, WIN_REASONS } from '@/lib/leads';

type TerminalStage = 'won' | 'lost';

/**
 * Reason picker shown when a lead is moved to a terminal stage (won/lost).
 * Confirms with the matching reason key (`win_reason`/`lost_reason`) and the
 * chosen reason value; the Confirm button stays disabled until one is picked.
 */
export function ReasonDialog({
    stage,
    onConfirm,
    onOpenChange,
}: {
    stage: TerminalStage | null;
    onConfirm: (key: 'win_reason' | 'lost_reason', reason: string) => void;
    onOpenChange: (open: boolean) => void;
}) {
    const [reason, setReason] = useState('');

    const reasons = stage === 'won' ? WIN_REASONS : LOST_REASONS;

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            setReason('');
        }

        onOpenChange(open);
    };

    return (
        <Dialog open={stage !== null} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Mark as {stage && STAGE_LABELS[stage]}
                    </DialogTitle>
                </DialogHeader>
                <Select value={reason} onValueChange={setReason}>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a reason" />
                    </SelectTrigger>
                    <SelectContent>
                        {reasons.map((r) => (
                            <SelectItem key={r.value} value={r.value}>
                                {r.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <DialogFooter>
                    <Button
                        disabled={!reason}
                        onClick={() => {
                            if (!reason) {
                                return;
                            }

                            onConfirm(
                                stage === 'won' ? 'win_reason' : 'lost_reason',
                                reason,
                            );
                            setReason('');
                        }}
                    >
                        Confirm
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
