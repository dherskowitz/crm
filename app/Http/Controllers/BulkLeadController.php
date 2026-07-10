<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\BulkLeadRequest;
use App\Jobs\ProcessBulkLeadAction;
use Illuminate\Http\RedirectResponse;

class BulkLeadController extends Controller
{
    public function update(BulkLeadRequest $request): RedirectResponse
    {
        $action = $request->validated('action');

        ProcessBulkLeadAction::dispatch(
            $request->user()->id,
            $request->validated('ids'),
            $action,
            $request->safe()->only(['owner_id', 'tag_id', 'stage', 'win_reason', 'lost_reason']),
        );

        return back()->with('status', 'bulk-action-queued');
    }
}
