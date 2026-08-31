<?php

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(fn () => Queue::fake());

it('rejects a bookmark with no url', function () {
    actingAs(User::factory()->create());
    postJson('/bookmarks', [])->assertJsonValidationErrors('url');
});

it('rejects a folder_id owned by someone else', function () {
    $u = User::factory()->create();
    $foreign = Folder::factory()->create();
    actingAs($u);
    postJson('/bookmarks', ['url' => 'https://a.test/', 'folder_id' => $foreign->id])
        ->assertJsonValidationErrors('folder_id');
});

it('accepts a null folder_id', function () {
    actingAs(User::factory()->create());
    postJson('/bookmarks', ['url' => 'https://a.test/', 'folder_id' => null])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertValid('folder_id');
});

it('rejects more than 50 tags', function () {
    actingAs(User::factory()->create());
    postJson('/bookmarks', ['url' => 'https://a.test/', 'tags' => array_fill(0, 51, 'x')])
        ->assertJsonValidationErrors('tags');
});

it('rejects an over-long url', function () {
    actingAs(User::factory()->create());
    postJson('/bookmarks', ['url' => 'https://a.test/'.str_repeat('a', 2100)])
        ->assertJsonValidationErrors('url');
});

it('rejects a folder create with no name', function () {
    actingAs(User::factory()->create());
    postJson('/folders', [])->assertJsonValidationErrors('name');
});

it('rejects a folder parent_id owned by someone else', function () {
    $u = User::factory()->create();
    $foreign = Folder::factory()->create();
    actingAs($u);
    postJson('/folders', ['name' => 'x', 'parent_id' => $foreign->id])
        ->assertJsonValidationErrors('parent_id');
});
