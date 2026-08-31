<?php

use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;

function token(User $u): string
{
    return $u->createToken('t')->plainTextToken;
}

it('rejects unauthenticated calls', function () {
    $this->getJson('/api/ping')->assertUnauthorized();
});

it('pings with token and returns the user', function () {
    $u = User::factory()->create();

    $this->withToken(token($u))->getJson('/api/ping')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('user.id', $u->id)
        ->assertJsonPath('user.email', $u->email)
        ->assertJsonPath('server.version', config('app.version'));
});

it('lists only the token user\'s folders and tags', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();

    Folder::factory()->for($u)->create(['name' => 'Mine', 'position' => 0]);
    Folder::factory()->for($other)->create(['name' => 'Theirs', 'position' => 0]);

    Tag::factory()->for($u)->create(['name' => 'Mine', 'name_lower' => 'mine']);
    Tag::factory()->for($other)->create(['name' => 'Theirs', 'name_lower' => 'theirs']);

    $this->withToken(token($u))->getJson('/api/folders')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Mine')
        ->assertJsonPath('0.position', 0);

    $this->withToken(token($u))->getJson('/api/tags')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Mine')
        ->assertJsonPath('0.bookmarks_count', 0);
});

it('filters tags by q case-insensitively', function () {
    $u = User::factory()->create();
    Tag::factory()->for($u)->create(['name' => 'alpha', 'name_lower' => 'alpha']);
    Tag::factory()->for($u)->create(['name' => 'beta', 'name_lower' => 'beta']);

    $this->withToken(token($u))->getJson('/api/tags?q=ALP')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'alpha');
});

it('applies the 120/min rate limit to api routes', function () {
    $u = User::factory()->create();

    $this->withToken(token($u))->getJson('/api/ping')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 120);
});
