<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\Source;
use App\Enums\Stage;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadFormSubmission;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as AuditActivity;

/**
 * Populates a realistic, demo-able dataset across every model: companies,
 * leads spread over all stages/sources/owners, timeline activities (with
 *
 * @mentions and pins), tasks, attachments, tags, saved views, automation
 * rules, and webhook form submissions.
 *
 * Idempotent at the dataset level — it no-ops if leads already exist so it is
 * safe to re-run without `migrate:fresh`.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (Lead::query()->withoutGlobalScopes()->exists()) {
            $this->command->warn('DemoDataSeeder skipped — leads already exist. Run `migrate:fresh --seed` to rebuild.');

            return;
        }

        /** @var Collection<int, User> $members */
        $members = User::query()->whereIn('role', ['admin', 'manager', 'member'])->get();
        $tags = Tag::all();

        $companies = Company::factory(15)->create();

        $this->seedActivePipeline($members, $companies, $tags);
        $this->seedQueue($companies, $tags);
        $this->seedClosed($members, $companies, $tags);
        $this->seedArchived($members);
        $this->seedDuplicates($members);

        $this->seedSavedViews($members);
        $this->seedAutomationRules();
        $this->seedAuditLog($members);
    }

    /**
     * A spread of Spatie audit-log entries so the admin audit screen has real
     * history. Written straight to the activity_log table because the model
     * events that normally populate it are suppressed during seeding.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedAuditLog(Collection $members): void
    {
        $admin = $members->firstWhere('role', Role::Admin) ?? $members->first();
        $manager = $members->firstWhere('role', Role::Manager) ?? $admin;

        $leads = Lead::query()->take(3)->get();
        $company = Company::query()->first();
        $task = Task::query()->first();

        if ($leads->isEmpty()) {
            return;
        }

        /**
         * @param  array<string, mixed>  $attributes
         * @param  array<string, mixed>  $old
         */
        $log = function (string $logName, string $event, Model $subject, ?User $causer, array $attributes, array $old, int $daysAgo) {
            $changes = array_filter([
                'attributes' => $attributes ?: null,
                'old' => $old ?: null,
            ]);

            AuditActivity::create([
                'log_name' => $logName,
                'description' => $event,
                'event' => $event,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'causer_type' => $causer?->getMorphClass(),
                'causer_id' => $causer?->getKey(),
                'attribute_changes' => $changes,
                'properties' => $changes,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        };

        $lead = $leads->first();
        $lead2 = $leads->get(1) ?? $lead;
        $lead3 = $leads->get(2) ?? $lead;

        $log('lead', 'created', $lead, $manager, ['name' => $lead->name, 'stage' => 'new'], [], 12);
        $log('lead', 'updated', $lead, $manager, ['stage' => 'qualified'], ['stage' => 'contacted'], 9);
        $log('lead', 'updated', $lead, $admin, ['owner_id' => $manager->id], ['owner_id' => null], 8);
        $log('lead', 'updated', $lead2, $manager, ['value' => '5000.00'], ['value' => null], 7);
        $log('user', 'updated', $manager, $admin, ['role' => 'manager'], ['role' => 'member'], 6);
        $log('lead', 'updated', $lead3, $admin, ['stage' => 'won'], ['stage' => 'in_progress'], 4);
        $log('lead', 'updated', $lead3, $admin, ['archived_at' => now()->subDays(3)->toDateTimeString()], ['archived_at' => null], 3);

        if ($company !== null) {
            $log('company', 'updated', $company, $admin, ['name' => $company->name.' (HQ)'], ['name' => $company->name], 5);
        }

        if ($task !== null) {
            $log('task', 'updated', $task, $manager, ['completed_at' => now()->subDays(2)->toDateTimeString()], ['completed_at' => null], 2);
        }
    }

    /**
     * Deliberately duplicated records so the Duplicates screen has clusters to
     * merge: two leads sharing an email, two sharing a phone, and two companies
     * whose names normalize to the same value.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedDuplicates(Collection $members): void
    {
        // Company cluster: same normalized name ("acmecorp"), different casing,
        // punctuation, and domain.
        Company::factory()->create(['name' => 'Acme Corp', 'domain' => 'acme.com']);
        Company::factory()->create(['name' => 'ACME Corp.', 'domain' => 'acme.io']);

        // Email cluster: an inbound web-form lead duplicating an existing one
        // (same address, different casing).
        Lead::factory()->stage(Stage::Contacted)->create([
            'name' => 'Jordan Rivera',
            'email' => 'jordan.rivera@northwind.example',
            'owner_id' => $members->random()->id,
            'created_at' => now()->subDays(12),
            'first_contacted_at' => now()->subDays(11),
            'last_activity_at' => now()->subDays(3),
        ]);
        Lead::factory()->unassigned()->stage(Stage::New)->create([
            'name' => 'Jordan Rivera',
            'email' => 'Jordan.Rivera@Northwind.example',
            'source' => Source::Website,
            'created_at' => now()->subDays(2),
            'last_activity_at' => now()->subDays(2),
        ]);

        // Phone cluster: same number ("5550102233"), different formatting.
        Lead::factory()->stage(Stage::New)->create([
            'name' => 'Sam Patel',
            'phone' => '(555) 010-2233',
            'owner_id' => $members->random()->id,
            'created_at' => now()->subDays(8),
            'last_activity_at' => now()->subDays(4),
        ]);
        Lead::factory()->stage(Stage::Qualified)->create([
            'name' => 'Samuel Patel',
            'phone' => '555-010-2233',
            'owner_id' => $members->random()->id,
            'created_at' => now()->subDays(5),
            'first_contacted_at' => now()->subDays(4),
            'last_activity_at' => now()->subDays(1),
        ]);
    }

    /**
     * Owned leads spread across the open stages, each with a believable
     * timeline, tasks, tags, and an occasional attachment.
     *
     * @param  Collection<int, User>  $members
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Tag>  $tags
     */
    private function seedActivePipeline(Collection $members, Collection $companies, Collection $tags): void
    {
        $openStages = [Stage::New, Stage::Contacted, Stage::Qualified, Stage::InProgress];

        foreach ($openStages as $stage) {
            Lead::factory(6)
                ->stage($stage)
                ->state(function () use ($members, $companies, $stage) {
                    $createdAt = now()->subDays(fake()->numberBetween(3, 28));

                    return [
                        'owner_id' => $members->random()->id,
                        'company_id' => $companies->random()->id,
                        'source' => fake()->randomElement(Source::cases()),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'first_contacted_at' => $stage === Stage::New
                            ? null
                            : $createdAt->addHours(fake()->numberBetween(2, 48)),
                        'last_activity_at' => $createdAt->addHours(fake()->numberBetween(2, 72)),
                    ];
                })
                ->create()
                ->each(function (Lead $lead) use ($members, $tags): void {
                    $lead->tags()->attach($tags->random(fake()->numberBetween(1, 2))->pluck('id'));
                    $this->seedTimeline($lead, $members);
                    $this->seedTasks($lead);

                    if (fake()->boolean(40)) {
                        Attachment::factory()->for($lead, 'attachable')->create([
                            'uploaded_by' => $lead->owner_id,
                        ]);
                    }
                });
        }
    }

    /**
     * Unassigned inbound leads from the website — the claimable queue. Each
     * gets a matching webhook submission record and a form-submission activity.
     *
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Tag>  $tags
     */
    private function seedQueue(Collection $companies, Collection $tags): void
    {
        Lead::factory(8)
            ->unassigned()
            ->stage(Stage::New)
            ->state(function () use ($companies) {
                $createdAt = now()->subDays(fake()->numberBetween(0, 12));

                return [
                    'company_id' => fake()->boolean(60) ? $companies->random()->id : null,
                    'source' => Source::Website,
                    'form_type' => fake()->randomElement(FormType::cases()),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'last_activity_at' => $createdAt,
                ];
            })
            ->create()
            ->each(function (Lead $lead) use ($tags): void {
                $lead->tags()->attach($tags->random()->id);

                LeadFormSubmission::create([
                    'submission_id' => fake()->unique()->uuid(),
                    'lead_id' => $lead->id,
                ]);

                Activity::log($lead, ActivityType::FormSubmission, [
                    'form_type' => $lead->form_type?->value,
                    'message' => fake()->sentence(),
                ], occurredAt: $lead->created_at);
            });
    }

    /**
     * Won and lost leads so reporting (conversion, win/lost reasons, value)
     * has something to chart.
     *
     * @param  Collection<int, User>  $members
     * @param  Collection<int, Company>  $companies
     * @param  Collection<int, Tag>  $tags
     */
    private function seedClosed(Collection $members, Collection $companies, Collection $tags): void
    {
        Lead::factory(7)
            ->won()
            ->state(function () use ($members, $companies) {
                $createdAt = now()->subDays(fake()->numberBetween(12, 28));
                $firstContacted = $createdAt->addHours(fake()->numberBetween(4, 72));
                $convertedAt = $firstContacted->addDays(fake()->numberBetween(2, 9));

                return [
                    'owner_id' => $members->random()->id,
                    'company_id' => $companies->random()->id,
                    'value' => fake()->randomFloat(2, 2000, 60000),
                    'created_at' => $createdAt,
                    'updated_at' => $convertedAt,
                    'first_contacted_at' => $firstContacted,
                    'converted_at' => $convertedAt,
                ];
            })
            ->create()
            ->each(function (Lead $lead) use ($members, $tags): void {
                $lead->tags()->attach($tags->random()->id);
                $this->seedTimeline($lead, $members);
                Activity::log($lead, ActivityType::StageChange, [
                    'from' => Stage::InProgress->value,
                    'to' => Stage::Won->value,
                ], $lead->owner, $lead->converted_at);
            });

        Lead::factory(5)
            ->lost()
            ->state(function () use ($members, $companies) {
                $createdAt = now()->subDays(fake()->numberBetween(10, 28));

                return [
                    'owner_id' => $members->random()->id,
                    'company_id' => $companies->random()->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->addDays(fake()->numberBetween(1, 8)),
                    'first_contacted_at' => $createdAt->addHours(fake()->numberBetween(4, 72)),
                ];
            })
            ->create()
            ->each(function (Lead $lead) use ($members): void {
                $this->seedTimeline($lead, $members);
            });
    }

    /**
     * A handful of stale, archived leads (past the retention window).
     *
     * @param  Collection<int, User>  $members
     */
    private function seedArchived(Collection $members): void
    {
        Lead::factory(3)
            ->archived()
            ->lost()
            ->state(function () use ($members) {
                $createdAt = now()->subMonths(fake()->numberBetween(20, 28));

                return [
                    'owner_id' => $members->random()->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'last_activity_at' => now()->subMonths(20),
                ];
            })
            ->create();
    }

    /**
     * Write a short, mixed timeline (notes/calls/emails) for a lead, pinning
     * one note and @mentioning a teammate on another.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedTimeline(Lead $lead, Collection $members): void
    {
        $author = $lead->owner ?? $members->random();

        Activity::factory()
            ->for($lead)
            ->state(fn () => [
                'user_id' => $author->id,
                'type' => ActivityType::Call,
                'body' => ['text' => fake()->sentence()],
                'occurred_at' => now()->subDays(fake()->numberBetween(3, 8)),
            ])
            ->create();

        Activity::factory()
            ->for($lead)
            ->pinned()
            ->state(fn () => [
                'user_id' => $author->id,
                'type' => ActivityType::Note,
                'body' => ['text' => fake()->paragraph()],
                'occurred_at' => now()->subDays(fake()->numberBetween(1, 3)),
            ])
            ->create();

        $mentioned = $members->where('id', '!=', $author->id)->random();

        $note = Activity::factory()
            ->for($lead)
            ->state(fn () => [
                'user_id' => $author->id,
                'type' => ActivityType::Email,
                'body' => ['text' => "Following up — looping in @{$mentioned->name}."],
                'occurred_at' => now()->subDay(),
            ])
            ->create();

        $note->mentionedUsers()->attach($mentioned->id);
    }

    /**
     * Give a lead a spread of tasks: one due soon, one overdue, one done.
     */
    private function seedTasks(Lead $lead): void
    {
        $owner = $lead->owner_id ?? User::query()->where('role', 'admin')->value('id');

        Task::factory()->for($lead)->state(fn () => ['owner_id' => $owner])->create();
        Task::factory()->for($lead)->overdue()->state(fn () => ['owner_id' => $owner])->create();
        Task::factory()->for($lead)->completed()->state(fn () => ['owner_id' => $owner])->create();
    }

    /**
     * A per-user saved view plus a shared "Hot queue" style filter.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedSavedViews(Collection $members): void
    {
        foreach ($members as $user) {
            SavedView::factory()->create([
                'user_id' => $user->id,
                'name' => 'My open leads',
                'filters' => ['mine' => true, 'stage' => Stage::InProgress->value],
            ]);
        }

        $owner = $members->firstWhere('role', Role::Manager) ?? $members->first();

        SavedView::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Unclaimed queue',
            'filters' => ['unassigned' => true, 'stage' => Stage::New->value],
        ]);
    }

    /**
     * Two automation rules, one per trigger/action pairing.
     */
    private function seedAutomationRules(): void
    {
        AutomationRule::create([
            'name' => 'Follow up on qualified leads',
            'trigger' => AutomationTrigger::StageChange,
            'conditions' => ['to' => Stage::Qualified->value],
            'action' => AutomationAction::CreateTask,
            'params' => ['title' => 'Send proposal', 'due_in_days' => 2],
            'is_active' => true,
        ]);

        AutomationRule::create([
            'name' => 'Nudge on idle leads',
            'trigger' => AutomationTrigger::LeadIdle,
            'conditions' => ['days' => 7],
            'action' => AutomationAction::SendNotification,
            'params' => ['message' => 'This lead has been idle for a week — time to reach out.'],
            'is_active' => true,
        ]);
    }
}
