<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    /**
     * Store the current filter set as a named view for the authed user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['array'],
        ]);

        $request->user()->savedViews()->create([
            'name' => $validated['name'],
            'filters' => $validated['filters'] ?? [],
        ]);

        return back()->with('status', 'saved-view-created');
    }

    /**
     * Delete a saved view the user owns.
     */
    public function destroy(SavedView $savedView): RedirectResponse
    {
        $this->authorize('delete', $savedView);

        $savedView->delete();

        return back()->with('status', 'saved-view-deleted');
    }
}
