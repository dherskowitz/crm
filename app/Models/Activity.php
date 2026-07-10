<?php

namespace App\Models;

use App\Enums\ActivityType;
use Carbon\CarbonInterface;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property ActivityType $type
 * @property array<string, mixed>|null $body
 * @property bool $is_pinned
 * @property Carbon $occurred_at
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['lead_id', 'user_id', 'type', 'body', 'is_pinned', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'body' => 'array',
            'is_pinned' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Write an activity for a lead, defaulting the actor and occurred_at.
     *
     * @param  array<string, mixed>  $body
     */
    public static function log(Lead $lead, ActivityType $type, array $body = [], ?User $actor = null, ?CarbonInterface $occurredAt = null): self
    {
        return self::create([
            'lead_id' => $lead->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'body' => $body,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Users @mentioned in this activity.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentionedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_user');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
