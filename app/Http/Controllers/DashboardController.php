<?php

namespace App\Http\Controllers;

use App\Enums\LostReason;
use App\Enums\Source;
use App\Enums\Stage;
use App\Enums\WinReason;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        [$from, $to] = $this->dateRange($request);

        $props = [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'conversion' => $this->conversion($user, $from, $to),
            'funnel' => $this->funnel($user, $from, $to),
            'wonValue' => $this->wonValue($user, $from, $to),
            'sources' => $this->sourceAttribution($user, $from, $to),
            'campaigns' => $this->campaignAttribution($user, $from, $to),
            'winReasons' => $this->winReasons($user, $from, $to),
            'lostReasons' => $this->lostReasons($user, $from, $to),
            'speedToLead' => $this->speedToLead($user, $from, $to),
            'coldLeads' => $this->coldLeads($user),
            'myTasks' => $this->myTasks($user),
        ];

        if ($user->hasCapability('team_reporting')) {
            $props['leaderboard'] = $this->leaderboard($user, $from, $to);
        }

        return Inertia::render('dashboard', $props);
    }

    /**
     * The inclusive [from, to] window read from the request, defaulting to the
     * last 30 days. Bounds are normalised to whole days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $to = $request->date('to') ?? Carbon::now();
        $from = $request->date('from') ?? (clone $to)->subDays(30);

        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ];
    }

    /**
     * A query scoped to the user's visible leads created within the range.
     *
     * @return Builder<Lead>
     */
    private function rangeQuery(User $user, Carbon $from, Carbon $to): Builder
    {
        return Lead::query()
            ->visibleTo($user)
            ->whereBetween('leads.created_at', [$from, $to]);
    }

    /**
     * Conversion rate (won / total) across the range.
     *
     * @return array{total: int, won: int, rate: float}
     */
    private function conversion(User $user, Carbon $from, Carbon $to): array
    {
        $total = $this->rangeQuery($user, $from, $to)->count();
        $won = $this->rangeQuery($user, $from, $to)->where('stage', Stage::Won)->count();

        return [
            'total' => $total,
            'won' => $won,
            'rate' => $total > 0 ? round($won / $total, 4) : 0.0,
        ];
    }

    /**
     * Per-stage counts plus an approximate average time-in-stage.
     *
     * Average days in pipeline is approximated as the mean number of days from
     * a won lead's created_at to its converted_at; this is the only point at
     * which the full pipeline duration is reliably known.
     *
     * @return array{stages: list<array<string, int|string>>, avgDaysToConvert: float|null}
     */
    private function funnel(User $user, Carbon $from, Carbon $to): array
    {
        $counts = $this->rangeQuery($user, $from, $to)
            ->select('stage', DB::raw('count(*) as aggregate'))
            ->groupBy('stage')
            ->pluck('aggregate', 'stage');

        $stages = $this->withCounts(Stage::cases(), $counts, 'stage');

        $avgDays = $this->rangeQuery($user, $from, $to)
            ->where('stage', Stage::Won)
            ->whereNotNull('converted_at')
            ->get(['created_at', 'converted_at'])
            ->map(fn (Lead $lead) => $lead->created_at->diffInDays($lead->converted_at))
            ->avg();

        return [
            'stages' => $stages,
            'avgDaysToConvert' => $avgDays !== null ? round((float) $avgDays, 1) : null,
        ];
    }

    /**
     * Map a set of enum cases to value/label/count rows, keying the value
     * under the given column and reading counts from the aggregate collection.
     *
     * @param  list<Stage|Source|WinReason|LostReason>  $cases
     * @param  Collection<int|string, mixed>  $counts
     * @return list<array<string, int|string>>
     */
    private function withCounts(array $cases, Collection $counts, string $key): array
    {
        return array_map(fn (Stage|Source|WinReason|LostReason $case): array => [
            $key => $case->value,
            'label' => $case->label(),
            'count' => (int) ($counts[$case->value] ?? 0),
        ], $cases);
    }

    private function wonValue(User $user, Carbon $from, Carbon $to): float
    {
        return (float) $this->rangeQuery($user, $from, $to)
            ->where('stage', Stage::Won)
            ->sum('value');
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function sourceAttribution(User $user, Carbon $from, Carbon $to): array
    {
        $counts = $this->rangeQuery($user, $from, $to)
            ->select('source', DB::raw('count(*) as aggregate'))
            ->groupBy('source')
            ->pluck('aggregate', 'source');

        return array_values(array_filter(
            $this->withCounts(Source::cases(), $counts, 'key'),
            fn (array $row) => $row['count'] > 0,
        ));
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    private function campaignAttribution(User $user, Carbon $from, Carbon $to): array
    {
        return array_values($this->rangeQuery($user, $from, $to)
            ->whereNotNull('utm_campaign')
            ->select('utm_campaign', DB::raw('count(*) as aggregate'))
            ->groupBy('utm_campaign')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Lead $lead) => [
                'key' => (string) $lead->utm_campaign,
                'label' => (string) $lead->utm_campaign,
                'count' => (int) $lead->getAttribute('aggregate'),
            ])
            ->all());
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function winReasons(User $user, Carbon $from, Carbon $to): array
    {
        $counts = $this->rangeQuery($user, $from, $to)
            ->whereNotNull('win_reason')
            ->select('win_reason', DB::raw('count(*) as aggregate'))
            ->groupBy('win_reason')
            ->pluck('aggregate', 'win_reason');

        return array_values(array_filter(
            $this->withCounts(WinReason::cases(), $counts, 'key'),
            fn (array $row) => $row['count'] > 0,
        ));
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function lostReasons(User $user, Carbon $from, Carbon $to): array
    {
        $counts = $this->rangeQuery($user, $from, $to)
            ->whereNotNull('lost_reason')
            ->select('lost_reason', DB::raw('count(*) as aggregate'))
            ->groupBy('lost_reason')
            ->pluck('aggregate', 'lost_reason');

        return array_values(array_filter(
            $this->withCounts(LostReason::cases(), $counts, 'key'),
            fn (array $row) => $row['count'] > 0,
        ));
    }

    /**
     * Average hours from creation to first contact for leads that have been
     * contacted within the range.
     *
     * @return array{avgHours: float|null, contacted: int}
     */
    private function speedToLead(User $user, Carbon $from, Carbon $to): array
    {
        $leads = $this->rangeQuery($user, $from, $to)
            ->whereNotNull('first_contacted_at')
            ->get(['created_at', 'first_contacted_at']);

        $avgHours = $leads
            ->map(fn (Lead $lead) => $lead->created_at->diffInHours($lead->first_contacted_at))
            ->avg();

        return [
            'avgHours' => $avgHours !== null ? round((float) $avgHours, 1) : null,
            'contacted' => $leads->count(),
        ];
    }

    /**
     * Team-wide leaderboard: leads won and conversion rate per owner within the
     * range. Only computed for users with the team_reporting capability.
     *
     * @return list<array{owner: string, total: int, won: int, rate: float, value: float}>
     */
    private function leaderboard(User $user, Carbon $from, Carbon $to): array
    {
        return array_values($this->rangeQuery($user, $from, $to)
            ->whereNotNull('owner_id')
            ->join('users', 'users.id', '=', 'leads.owner_id')
            ->select([
                'users.name as owner',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when leads.stage = 'won' then 1 else 0 end) as won"),
                DB::raw("sum(case when leads.stage = 'won' then leads.value else 0 end) as won_value"),
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('won')
            ->get()
            ->map(function (Lead $row) {
                $total = (int) $row->getAttribute('total');
                $won = (int) $row->getAttribute('won');

                return [
                    'owner' => (string) $row->getAttribute('owner'),
                    'total' => $total,
                    'won' => $won,
                    'rate' => $total > 0 ? round($won / $total, 4) : 0.0,
                    'value' => (float) $row->getAttribute('won_value'),
                ];
            })
            ->all());
    }

    /**
     * The current user's open tasks, soonest due first — the actionable list
     * surfaced on the dashboard so reps can clear what's due without digging
     * into each lead.
     *
     * @return list<array<string, mixed>>
     */
    private function myTasks(User $user): array
    {
        return array_values(Task::query()
            ->with('lead:id,name')
            ->where('owner_id', $user->id)
            ->open()
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(6)
            ->get()
            ->map(fn (Task $task) => TaskPresenter::present($task))
            ->all());
    }

    /**
     * Active (non-terminal, non-archived) leads with no activity for longer
     * than the configured cold-lead threshold, scoped to what the user can see.
     *
     * @return list<array{id: int, name: string, owner: string|null, last_activity_at: string|null}>
     */
    private function coldLeads(User $user): array
    {
        $threshold = Carbon::now()->subDays((int) config('crm.cold_lead_days'));

        return array_values(Lead::query()
            ->visibleTo($user)
            ->active()
            ->nonTerminal()
            ->where('last_activity_at', '<', $threshold)
            ->with('owner:id,name')
            ->orderBy('last_activity_at')
            ->limit(10)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'owner' => $lead->owner?->name,
                'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
            ])
            ->all());
    }
}
