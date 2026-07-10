<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:archive-stale-leads')]
#[Description('Soft-archive active leads with no activity beyond the retention window')]
class ArchiveStaleLeads extends Command
{
    public function handle(): int
    {
        $months = (int) config('crm.retention_months');
        $cutoff = now()->subMonths($months);

        Lead::query()
            ->whereNull('archived_at')
            ->where('last_activity_at', '<', $cutoff)
            ->chunkById(200, function ($leads): void {
                foreach ($leads as $lead) {
                    $lead->update(['archived_at' => now()]);
                }
            });

        return self::SUCCESS;
    }
}
