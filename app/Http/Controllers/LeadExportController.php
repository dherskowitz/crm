<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportController extends Controller
{
    /**
     * Stream the user's visible, filtered leads as CSV. Mirrors the filtering
     * in LeadController@index so the export matches what's on screen.
     */
    public function index(Request $request): StreamedResponse
    {
        $this->authorize('export', Lead::class);

        $user = $request->user();

        $query = Lead::query()
            ->visibleTo($user)
            ->active()
            ->with(['company:id,name', 'owner:id,name'])
            ->filter($request, $user)
            ->orderByDesc('last_activity_at');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Name', 'Email', 'Phone', 'Company', 'Stage', 'Owner', 'Value', 'Source', 'Created At']);

            $query->chunk(200, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, [
                        $lead->name,
                        $lead->email,
                        $lead->phone,
                        $lead->company?->name,
                        $lead->stage->label(),
                        $lead->owner?->name,
                        $lead->value,
                        $lead->source->label(),
                        $lead->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'leads.csv', $headers);
    }
}
