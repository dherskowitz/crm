<?php

use App\Actions\Leads\ImportLeadsFromCsv;
use App\Enums\Source;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('only admins can reach the import screen', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('leads.import'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('leads.import'))->assertOk();
});

test('uploading a csv returns its headers for mapping', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $file = UploadedFile::fake()->createWithContent(
        'leads.csv',
        "Full Name,Email,Phone\nJane,jane@acme.com,555\n",
    );

    $this->actingAs($admin)
        ->post(route('leads.import.preview'), ['file' => $file])
        ->assertInertia(fn ($page) => $page
            ->component('leads/import')
            ->where('headers', ['Full Name', 'Email', 'Phone'])
            ->has('storedPath'));
});

test('the importer creates leads and skips duplicates within the file and against existing leads', function () {
    Storage::fake('local');
    Lead::factory()->create(['email' => 'existing@acme.com', 'source' => Source::Website]);

    $csv = "name,email\nJane,jane@acme.com\nDup,jane@acme.com\nExisting,existing@acme.com\nNoEmail,\n";
    Storage::disk('local')->put('imports/test.csv', $csv);

    $result = app(ImportLeadsFromCsv::class)('local', 'imports/test.csv', [
        'name' => 'name',
        'email' => 'email',
    ]);

    // Jane + NoEmail created; the in-file dupe and the existing email skipped.
    expect($result['created'])->toBe(2)
        ->and($result['skipped'])->toBe(2)
        ->and(Lead::where('email', 'jane@acme.com')->count())->toBe(1);

    expect(Lead::where('source', Source::Import->value)->count())->toBe(2);
});

test('the import endpoint rejects a path outside the imports directory', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('leads.import.store'), [
        'path' => '../../.env',
        'mapping' => ['name' => 'name'],
    ])->assertSessionHasErrors('path');
});

test('the import endpoint queues the job and is admin only', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/test.csv', "name,email\nJane,jane@acme.com\n");

    $payload = ['path' => 'imports/test.csv', 'mapping' => ['name' => 'name', 'email' => 'email']];

    $this->actingAs(User::factory()->member()->create())
        ->post(route('leads.import.store'), $payload)->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('leads.import.store'), $payload)
        ->assertRedirect(route('leads.index'));

    // Queue is sync in tests, so the lead was actually imported.
    expect(Lead::where('email', 'jane@acme.com')->count())->toBe(1);
});
