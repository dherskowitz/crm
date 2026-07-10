<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound Lead Webhook
    |--------------------------------------------------------------------------
    |
    | The shared secret and replay tolerance for the signed webhook the
    | website posts form submissions to. The secret is rotatable via env.
    |
    */

    'webhook' => [
        'secret' => env('LEAD_WEBHOOK_SECRET'),
        'tolerance' => (int) env('LEAD_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('CRM_ATTACHMENT_DISK', 'local'),
        'max_size' => (int) env('CRM_ATTACHMENT_MAX_SIZE', 10 * 1024 * 1024),
        'allowed_mimes' => [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle thresholds (section-16 defaults)
    |--------------------------------------------------------------------------
    */

    'cold_lead_days' => (int) env('CRM_COLD_LEAD_DAYS', 14),
    'retention_months' => (int) env('CRM_RETENTION_MONTHS', 18),

];
