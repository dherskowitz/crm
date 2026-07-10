<?php

namespace App\Actions\Companies;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class UnmergeCompanies
{
    /**
     * Reverse a company merge using the snapshot stored on the loser: move its
     * leads back, null the domain if the merge backfilled it, and un-hide the
     * loser by clearing merged_into_id.
     */
    public function __invoke(Company $loser): Company
    {
        return DB::transaction(function () use ($loser) {
            /** @var array<string, mixed> $snapshot */
            $snapshot = $loser->merge_snapshot ?? [];
            $survivor = Company::withoutGlobalScopes()->findOrFail($loser->merged_into_id);

            Lead::withoutGlobalScopes()
                ->whereIn('id', $snapshot['lead_ids'] ?? [])
                ->update(['company_id' => $loser->id]);

            if (! empty($snapshot['backfilled_domain'])) {
                $survivor->forceFill(['domain' => null])->save();
            }

            $loser->forceFill(['merged_into_id' => null, 'merge_snapshot' => null])->save();

            return $loser;
        });
    }
}
