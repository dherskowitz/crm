<?php

namespace App\Http\Requests\Webhooks;

use App\Enums\FormType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LeadWebhookRequest extends FormRequest
{
    /**
     * Authorization is handled by the HMAC signature middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = (int) config('crm.attachments.max_size');
        $allowedMimes = implode(',', config('crm.attachments.allowed_mimes'));
        // base64 inflates by ~4/3; bound the encoded string accordingly.
        $maxBase64 = (int) (ceil($maxSize / 3) * 4) + 1024;

        return [
            'submission_id' => ['required', 'uuid'],
            'form_type' => ['required', new Enum(FormType::class)],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],

            'company' => ['nullable', 'array'],
            'company.name' => ['nullable', 'string', 'max:255'],
            'company.domain' => ['nullable', 'string', 'max:255'],

            'message' => ['nullable', 'string'],

            'attribution' => ['nullable', 'array'],
            'attribution.utm_source' => ['nullable', 'string', 'max:255'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:255'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:255'],
            'attribution.utm_term' => ['nullable', 'string', 'max:255'],
            'attribution.utm_content' => ['nullable', 'string', 'max:255'],
            'attribution.referrer' => ['nullable', 'string', 'max:2048'],
            'attribution.landing_page' => ['nullable', 'string', 'max:2048'],

            'attachments' => ['nullable', 'array'],
            'attachments.*.filename' => ['required', 'string', 'max:255'],
            'attachments.*.mime' => ['required', 'string', 'in:'.$allowedMimes],
            'attachments.*.size' => ['required', 'integer', 'max:'.$maxSize],
            'attachments.*.data_base64' => ['required', 'string', 'max:'.$maxBase64],

            'submitted_at' => ['nullable', 'date'],
        ];
    }
}
