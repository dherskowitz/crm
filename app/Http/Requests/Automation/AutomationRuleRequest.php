<?php

namespace App\Http\Requests\Automation;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\Stage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_users') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', new Enum(AutomationTrigger::class)],
            'action' => ['required', new Enum(AutomationAction::class)],

            'conditions' => ['nullable', 'array'],
            'conditions.to' => ['nullable', new Enum(Stage::class)],
            'conditions.days' => ['nullable', 'integer', 'min:1'],

            'params' => ['nullable', 'array'],
            'params.title' => ['nullable', 'string', 'max:255'],
            'params.due_in_days' => ['nullable', 'integer', 'min:0'],
            'params.message' => ['nullable', 'string', 'max:500'],

            'is_active' => ['boolean'],
        ];
    }
}
