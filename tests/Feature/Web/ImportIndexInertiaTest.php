<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('renders the Import/Index page with the props it needs', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->get(route('import.index'))->assertInertia(fn ($p) => $p
        ->component('Import/Index')
        ->has('formats')
        ->where('activeImport', null));
});
