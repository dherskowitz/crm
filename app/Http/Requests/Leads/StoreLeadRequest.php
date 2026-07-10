<?php

namespace App\Http\Requests\Leads;

use App\Http\Requests\Concerns\NormalizesEmail;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    use NormalizesEmail;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
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
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }
}
