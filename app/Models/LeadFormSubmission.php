<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $submission_id
 * @property int|null $lead_id
 */
class LeadFormSubmission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['submission_id', 'lead_id'];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
