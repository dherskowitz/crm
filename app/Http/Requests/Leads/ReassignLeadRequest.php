<?php

namespace App\Http\Requests\Leads;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reassign', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
        ];
    }
}
