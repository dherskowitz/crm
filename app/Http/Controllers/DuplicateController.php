<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DuplicateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->hasCapability('merge_duplicates'), 403);

        $leads = Lead::query()
            ->visibleTo($user)
            ->with(['company:id,name', 'owner:id,name'])
            ->get();

        return Inertia::render('duplicates/index', [
            'leadClusters' => $this->leadClusters($leads),
            'companyClusters' => $this->companyClusters(),
        ]);
    }

    /**
     * Group leads sharing a normalized email or phone, keeping only clusters
     * with more than one member.
     *
     * @param  Collection<int, Lead>  $leads
     * @return list<array{key: string, type: string, value: string, leads: list<array<string, mixed>>}>
     */
    private function leadClusters(Collection $leads): array
    {
        $clusters = [];

        foreach (['email' => 'email', 'phone' => 'phone'] as $type => $attribute) {
            $grouped = $leads
                ->filter(fn (Lead $lead) => filled($lead->{$attribute}))
                ->groupBy(fn (Lead $lead) => $this->normalize((string) $lead->{$attribute}))
                ->filter(fn (Collection $group) => $group->count() > 1);

            foreach ($grouped as $value => $group) {
                $clusters[] = [
                    'key' => "{$type}:{$value}",
                    'type' => $type,
                    'value' => (string) $group->first()?->{$attribute},
                    'leads' => array_values($group->map(fn (Lead $lead) => $this->presentLead($lead))->all()),
                ];
            }
        }

        return $clusters;
    }

    /**
     * Group companies sharing a normalized name, keeping only clusters with
     * more than one member.
     *
     * @return list<array{key: string, value: string, companies: list<array<string, mixed>>}>
     */
    private function companyClusters(): array
    {
        return array_values(Company::query()
            ->withCount('leads')
            ->get()
            ->groupBy(fn (Company $company) => $this->normalize($company->name))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group, string $value) => [
                'key' => "company:{$value}",
                'value' => (string) $group->first()?->name,
                'companies' => array_values($group->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'domain' => $company->domain,
                    'leads_count' => $company->leads_count,
                ])->all()),
            ])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLead(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'stage' => $lead->stage->value,
            'company' => $lead->company?->only(['id', 'name']),
            'owner' => $lead->owner?->only(['id', 'name']),
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
    }
}
