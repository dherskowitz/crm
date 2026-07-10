<?php

namespace App\Models;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property AutomationTrigger $trigger
 * @property array<string, mixed>|null $conditions
 * @property AutomationAction $action
 * @property array<string, mixed>|null $params
 * @property bool $is_active
 */
class AutomationRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'trigger', 'conditions', 'action', 'params', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'action' => AutomationAction::class,
            'conditions' => 'array',
            'params' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<AutomationRule>  $query
     */
    public function scopeActiveFor(Builder $query, AutomationTrigger $trigger): void
    {
        $query->where('is_active', true)->where('trigger', $trigger->value);
    }
}
