<?php

use App\Models\Lead;

test('it soft-archives leads idle beyond the retention window', function () {
    config()->set('crm.retention_months', 18);

    $stale = Lead::factory()->create([
        'last_activity_at' => now()->subMonths(20),
        'archived_at' => null,
    ]);

    $recent = Lead::factory()->create([
        'last_activity_at' => now()->subMonths(2),
        'archived_at' => null,
    ]);

    $this->artisan('app:archive-stale-leads')->assertSuccessful();

    expect($stale->fresh()->archived_at)->not->toBeNull()
        ->and($recent->fresh()->archived_at)->toBeNull();
});

test('it never re-touches an already-archived lead', function () {
    config()->set('crm.retention_months', 18);

    $archived = Lead::factory()->create([
        'last_activity_at' => now()->subMonths(30),
        'archived_at' => now()->subMonths(5),
    ]);

    $originalArchivedAt = $archived->archived_at;

    $this->artisan('app:archive-stale-leads')->assertSuccessful();

    expect($archived->fresh()->archived_at->toIso8601String())
        ->toBe($originalArchivedAt->toIso8601String());
});
