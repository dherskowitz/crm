<?php

use App\Models\User;

test('the help screen is available to any authenticated user', function () {
    $this->actingAs(User::factory()->member()->create())
        ->get(route('help'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('help/index'));
});

test('guests cannot reach the help screen', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});
