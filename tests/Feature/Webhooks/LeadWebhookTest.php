<?php

use App\Enums\ActivityType;
use App\Enums\Source;
use App\Enums\Stage;
use App\Models\Lead;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['crm.webhook.secret' => 'test-secret']);
    Storage::fake('local');
});

/**
 * Post a signed webhook with control over the exact raw body that is signed.
 */
function postSignedWebhook(array $payload, ?string $secret = 'test-secret', ?int $timestamp = null): TestResponse
{
    $body = json_encode($payload);
    $timestamp ??= time();
    $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

    if ($secret !== null) {
        $headers['X-CRM-Timestamp'] = (string) $timestamp;
        $headers['X-CRM-Signature'] = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    return test()->call('POST', '/api/webhooks/leads', [], [], [], transformHeadersToServerVars($headers), $body);
}

function transformHeadersToServerVars(array $headers): array
{
    $server = [];
    foreach ($headers as $name => $value) {
        $name = strtoupper(str_replace('-', '_', $name));
        $server[in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH']) ? $name : 'HTTP_'.$name] = $value;
    }

    return $server;
}

function validPayload(array $overrides = []): array
{
    return array_merge([
        'submission_id' => (string) Str::uuid(),
        'form_type' => 'contact',
        'name' => 'Jane Doe',
        'email' => 'jane@acme.com',
        'phone' => '+1 555 0100',
        'company' => ['name' => 'Acme Inc', 'domain' => 'acme.com'],
        'message' => 'I would like a demo',
        'attribution' => ['utm_source' => 'google', 'utm_campaign' => 'spring'],
        'submitted_at' => now()->toIso8601String(),
    ], $overrides);
}

test('a valid signed submission creates an unassigned website lead', function () {
    $response = postSignedWebhook(validPayload());

    $response->assertStatus(202);

    $lead = Lead::first();
    expect($lead)->not->toBeNull()
        ->and($lead->email)->toBe('jane@acme.com')
        ->and($lead->stage)->toBe(Stage::New)
        ->and($lead->source)->toBe(Source::Website)
        ->and($lead->owner_id)->toBeNull()
        ->and($lead->utm_source)->toBe('google')
        ->and($lead->company->name)->toBe('Acme Inc');

    expect($lead->activities()->where('type', ActivityType::FormSubmission->value)->count())->toBe(1);
});

test('an invalid signature is rejected', function () {
    $payload = validPayload();
    $body = json_encode($payload);

    $response = test()->call('POST', '/api/webhooks/leads', [], [], [], transformHeadersToServerVars([
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-CRM-Timestamp' => (string) time(),
        'X-CRM-Signature' => 'deadbeef',
    ]), $body);

    $response->assertStatus(401);
    expect(Lead::count())->toBe(0);
});

test('missing signature headers are rejected', function () {
    postSignedWebhook(validPayload(), secret: null)->assertStatus(401);
});

test('a stale timestamp is rejected', function () {
    postSignedWebhook(validPayload(), timestamp: time() - 1000)->assertStatus(401);
});

test('an invalid payload fails validation', function () {
    postSignedWebhook(validPayload(['email' => 'not-an-email']))->assertStatus(422);
    postSignedWebhook(validPayload(['form_type' => 'nonsense']))->assertStatus(422);
});

test('repeat submission ids are idempotent', function () {
    $id = (string) Str::uuid();

    postSignedWebhook(validPayload(['submission_id' => $id]))->assertStatus(202);
    postSignedWebhook(validPayload(['submission_id' => $id]))->assertStatus(202);

    expect(Lead::count())->toBe(1);
});

test('a new submission for an existing non-terminal lead is appended, not duplicated', function () {
    postSignedWebhook(validPayload())->assertStatus(202);
    postSignedWebhook(validPayload(['message' => 'Following up']))->assertStatus(202);

    expect(Lead::where('email', 'jane@acme.com')->count())->toBe(1);

    $lead = Lead::where('email', 'jane@acme.com')->first();
    expect($lead->activities()->where('type', ActivityType::FormSubmission->value)->count())->toBe(2);
});

test('a submission for an email whose only lead is terminal creates a new lead', function () {
    Lead::factory()->lost()->create(['email' => 'jane@acme.com']);

    postSignedWebhook(validPayload())->assertStatus(202);

    expect(Lead::where('email', 'jane@acme.com')->count())->toBe(2);
});

test('a malicious attachment filename cannot escape the storage directory', function () {
    $payload = validPayload([
        'attachments' => [[
            'filename' => '../../../../etc/evil.pdf',
            'mime' => 'application/pdf',
            'size' => 5,
            'data_base64' => base64_encode('hello'),
        ]],
    ]);

    postSignedWebhook($payload)->assertStatus(202);

    $attachment = Lead::first()->attachments()->first();
    expect($attachment->path)->not->toContain('..')
        ->and($attachment->filename)->toBe('evil.pdf')
        ->and($attachment->path)->toStartWith('attachments/');
});

test('base64 attachments are decoded and stored', function () {
    $payload = validPayload([
        'attachments' => [[
            'filename' => 'brief.pdf',
            'mime' => 'application/pdf',
            'size' => 5,
            'data_base64' => base64_encode('hello'),
        ]],
    ]);

    postSignedWebhook($payload)->assertStatus(202);

    $lead = Lead::first();
    expect($lead->attachments()->count())->toBe(1);

    $attachment = $lead->attachments()->first();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->mime)->toBe('application/pdf');
});
