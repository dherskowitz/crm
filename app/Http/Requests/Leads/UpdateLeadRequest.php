<?php

namespace App\Http\Requests\Leads;

use App\Enums\LostReason;
use App\Enums\Stage;
use App\Enums\WinReason;
use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateLeadRequest extends FormRequest
{
    use NormalizesEmail;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'stage' => ['required', new Enum(Stage::class)],
            'lost_reason' => ['nullable', 'required_if:stage,'.Stage::Lost->value, new Enum(LostReason::class)],
            'win_reason' => ['nullable', 'required_if:stage,'.Stage::Won->value, new Enum(WinReason::class)],
        ];
    }
}
