<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('creates a token and shows the plaintext once', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    actingAs($user)->post('/settings/tokens', ['name' => 'Laptop'])
        ->assertRedirect()
        ->assertSessionHas('flash.token');

    expect($user->tokens()->count())->toBe(1);
});

it('revokes only your own token', function () {
    $a = User::factory()->create(['email_verified_at' => now()]);
    $b = User::factory()->create(['email_verified_at' => now()]);

    $mine = $a->createToken('a')->accessToken;
    $theirs = $b->createToken('b')->accessToken;

    actingAs($a)->delete("/settings/tokens/{$theirs->id}")->assertNotFound();
    actingAs($a)->delete("/settings/tokens/{$mine->id}")->assertRedirect();

    expect($a->tokens()->count())->toBe(0);
});

it('renders the tokens page with a tokens prop for the owner', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->createToken('existing');

    actingAs($user)->get('/settings/tokens')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Tokens')
            ->has('tokens', 1)
        );
});

it('redirects a guest away from the tokens page', function () {
    $this->get('/settings/tokens')->assertRedirect('/login');
});
