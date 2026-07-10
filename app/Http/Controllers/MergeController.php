<?php

namespace App\Http\Controllers;

use App\Actions\Companies\MergeCompanies;
use App\Actions\Companies\UnmergeCompanies;
use App\Actions\Leads\MergeLeads;
use App\Actions\Leads\UnmergeLeads;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MergeController extends Controller
{
    public function mergeLeads(Request $request, MergeLeads $mergeLeads): RedirectResponse
    {
        $validated = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:loser_id', 'exists:leads,id'],
            'loser_id' => ['required', 'integer', 'exists:leads,id'],
        ]);

        $survivor = Lead::findOrFail((int) $validated['survivor_id']);
        $loser = Lead::findOrFail((int) $validated['loser_id']);

        $this->authorize('merge', $survivor);

        $mergeLeads($survivor, $loser, $request->user());

        return back()->with('status', 'leads-merged');
    }

    public function mergeCompanies(Request $request, MergeCompanies $mergeCompanies): RedirectResponse
    {
        $validated = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:loser_id', 'exists:companies,id'],
            'loser_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $survivor = Company::findOrFail((int) $validated['survivor_id']);
        $loser = Company::findOrFail((int) $validated['loser_id']);

        $this->authorize('merge', $survivor);

        $mergeCompanies($survivor, $loser);

        return back()->with('status', 'companies-merged');
    }

    public function unmergeLeads(Request $request, UnmergeLeads $unmergeLeads): RedirectResponse
    {
        $validated = $request->validate([
            'loser_id' => ['required', 'integer'],
        ]);

        $loser = Lead::withoutGlobalScopes()
            ->whereNotNull('merged_into_id')
            ->findOrFail((int) $validated['loser_id']);
        $survivor = Lead::withoutGlobalScopes()->findOrFail($loser->merged_into_id);

        $this->authorize('merge', $survivor);

        $unmergeLeads($loser);

        return back()->with('status', 'leads-unmerged');
    }

    public function unmergeCompanies(Request $request, UnmergeCompanies $unmergeCompanies): RedirectResponse
    {
        $validated = $request->validate([
            'loser_id' => ['required', 'integer'],
        ]);

        $loser = Company::withoutGlobalScopes()
            ->whereNotNull('merged_into_id')
            ->findOrFail((int) $validated['loser_id']);
        $survivor = Company::withoutGlobalScopes()->findOrFail($loser->merged_into_id);

        $this->authorize('merge', $survivor);

        $unmergeCompanies($loser);

        return back()->with('status', 'companies-unmerged');
    }
}
