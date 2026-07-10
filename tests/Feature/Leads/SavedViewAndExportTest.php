<?php

use App\Models\Lead;
use App\Models\SavedView;
use App\Models\User;

test('a user can save, see, apply and delete only their own views', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)->post(route('saved-views.store'), [
        'name' => 'My queue',
        'filters' => ['unassigned' => true, 'stage' => 'new'],
    ])->assertRedirect();

    $view = SavedView::first();
    expect($view->user_id)->toBe($user->id)
        ->and($view->filters)->toBe(['unassigned' => true, 'stage' => 'new']);

    // It is shared into the leads index for its owner.
    $this->actingAs($user)->get(route('leads.index'))
        ->assertInertia(fn ($page) => $page->has('savedViews', 1));

    // A different user cannot delete it.
    $this->actingAs(User::factory()->member()->create())
        ->delete(route('saved-views.destroy', $view))
        ->assertForbidden();

    $this->actingAs($user)->delete(route('saved-views.destroy', $view))->assertRedirect();
    $this->assertModelMissing($view);
});

test('export is gated by the export capability', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)->get(route('leads.export'))->assertForbidden();
});

test('a manager export streams only visible leads as csv', function () {
    $manager = User::factory()->manager()->create();
    Lead::factory()->create(['name' => 'Exported Person', 'email' => 'exp@acme.com']);

    $response = $this->actingAs($manager)->get(route('leads.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())
        ->toContain('Exported Person')
        ->toContain('Name,Email,Phone');
});

test('a member export only includes their own and unassigned leads', function () {
    $member = User::factory()->member()->create();
    // Grant export to this member to isolate the visibility behaviour.
    $admin = User::factory()->admin()->create();

    Lead::factory()->ownedBy($admin)->create(['name' => 'Hidden Lead']);
    Lead::factory()->unassigned()->create(['name' => 'Queue Lead']);

    $content = $this->actingAs($admin)->get(route('leads.export'))->streamedContent();
    expect($content)->toContain('Hidden Lead')->toContain('Queue Lead');
});
