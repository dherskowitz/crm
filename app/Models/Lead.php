<?php

namespace App\Models;

use App\Enums\FormType;
use App\Enums\LostReason;
use App\Enums\Source;
use App\Enums\Stage;
use App\Enums\WinReason;
use App\Models\Scopes\NotMergedScope;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Source $source
 * @property FormType|null $form_type
 * @property Stage $stage
 * @property string|null $value
 * @property CarbonInterface|null $expected_close_date
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $referrer
 * @property string|null $landing_page
 * @property int|null $owner_id
 * @property LostReason|null $lost_reason
 * @property WinReason|null $win_reason
 * @property CarbonInterface|null $first_contacted_at
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface|null $converted_at
 * @property CarbonInterface|null $archived_at
 * @property int|null $merged_into_id
 * @property array<string, mixed>|null $merge_snapshot
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[ScopedBy(NotMergedScope::class)]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id', 'name', 'email', 'phone',
        'source', 'form_type', 'stage', 'value', 'expected_close_date', 'owner_id',
        'lost_reason', 'win_reason',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'referrer', 'landing_page',
        'first_contacted_at', 'last_activity_at', 'converted_at', 'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => Source::class,
            'form_type' => FormType::class,
            'stage' => Stage::class,
            'lost_reason' => LostReason::class,
            'win_reason' => WinReason::class,
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'first_contacted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'converted_at' => 'datetime',
            'archived_at' => 'datetime',
            'merge_snapshot' => 'array',
        ];
    }

    /**
     * Configure which lead attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lead')
            ->logOnly(['name', 'email', 'stage', 'owner_id', 'value', 'archived_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Restrict the query to the leads a user is allowed to see: everything for
     * admins and managers; own leads plus the unassigned queue for members.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin() || $user->isManager()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)->orWhereNull('owner_id');
        });
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeNonTerminal(Builder $query): void
    {
        $query->whereNotIn('stage', [Stage::Won->value, Stage::Lost->value]);
    }

    /**
     * Apply the shared index/export lead filters: stage, unassigned, mine,
     * owner_id, tag_id, and a name/email search.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeFilter(Builder $query, Request $request, User $user): void
    {
        $query
            ->when($request->string('stage')->isNotEmpty(), fn ($q) => $q->where('stage', $request->string('stage')->toString()))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('owner_id'))
            ->when($request->boolean('mine'), fn ($q) => $q->where('owner_id', $user->id))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('tag_id'), fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($request->integer('tag_id'))))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                // caseSensitive: false → ILIKE on Postgres, LIKE elsewhere.
                $q->where(fn ($qq) => $qq
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false));
            });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isUnassigned(): bool
    {
        return $this->owner_id === null;
    }

    /**
     * Whether the given user is allowed to see this lead, mirroring the
     * visibleTo query scope for single-record (policy) checks.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $this->owner_id === null || $this->owner_id === $user->id;
    }
}
