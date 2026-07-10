<?php

namespace App\Http\Requests\Activities;

use App\Enums\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                ActivityType::Note->value,
                ActivityType::Call->value,
                ActivityType::Email->value,
            ])],
            'body' => ['required', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
