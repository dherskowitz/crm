<?php

namespace App\Actions\Companies;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class MergeCompanies
{
    /**
     * Soft-merge the loser company into the survivor: re-point the loser's
     * leads onto the survivor, backfill any null survivor fields, and point the
     * loser at the survivor via merged_into_id. The loser row is kept so the
     * merge stays reversible; the NotMergedScope global scope hides it.
     */
    public function __invoke(Company $survivor, Company $loser): Company
    {
        return DB::transaction(function () use ($survivor, $loser) {
            // Capture what this merge moves/changes before it happens, so it can
            // be reversed exactly (see UnmergeCompanies).
            $leadIds = Lead::withoutGlobalScopes()
                ->where('company_id', $loser->id)
                ->pluck('id')->all();

            $backfilledDomain = $survivor->domain === null && $loser->domain !== null;

            Lead::withoutGlobalScopes()
                ->whereIn('id', $leadIds)
                ->update(['company_id' => $survivor->id]);

            $survivor->domain ??= $loser->domain;

            $survivor->save();

            $loser->forceFill([
                'merged_into_id' => $survivor->id,
                'merge_snapshot' => [
                    'lead_ids' => $leadIds,
                    'backfilled_domain' => $backfilledDomain,
                ],
            ])->save();

            return $survivor;
        });
    }
}
