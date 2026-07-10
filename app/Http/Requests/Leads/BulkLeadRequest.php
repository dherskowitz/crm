<?php

namespace App\Http\Requests\Leads;

use App\Enums\LostReason;
use App\Enums\Stage;
use App\Enums\WinReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BulkLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [Rule::exists('leads', 'id')],
            'action' => ['required', Rule::in(['reassign', 'tag', 'stage', 'delete'])],

            'owner_id' => ['nullable', 'required_if:action,reassign', Rule::exists('users', 'id')],
            'tag_id' => ['nullable', 'required_if:action,tag', Rule::exists('tags', 'id')],
            'stage' => ['nullable', 'required_if:action,stage', new Enum(Stage::class)],
            'win_reason' => ['nullable', 'required_if:stage,'.Stage::Won->value, new Enum(WinReason::class)],
            'lost_reason' => ['nullable', 'required_if:stage,'.Stage::Lost->value, new Enum(LostReason::class)],
        ];
    }
}
