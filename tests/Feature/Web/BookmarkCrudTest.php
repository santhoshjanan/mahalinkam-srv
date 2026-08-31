<?php

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

it('renders the index with the user\'s bookmarks only', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    Bookmark::factory()->for($u)->count(2)->create();
    Bookmark::factory()->count(5)->create();
    actingAs($u)->get('/')->assertInertia(fn ($p) => $p->component('Bookmarks/Index')->has('bookmarks.data', 2));
});

it('creates a bookmark and flashes saved', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    actingAs($u);
    post('/bookmarks', ['url' => 'https://a.test/x'])
        ->assertRedirect()->assertSessionHas('flash.kind', 'saved');
    $this->assertDatabaseHas('bookmarks', ['user_id' => $u->id, 'normalized_url' => 'https://a.test/x']);
});

it('flashes already_saved on a duplicate', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    actingAs($u);
    post('/bookmarks', ['url' => 'https://a.test/x']);
    post('/bookmarks', ['url' => 'https://a.test/x'])->assertSessionHas('flash.kind', 'already_saved');
});

it('forbids editing another user\'s bookmark', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Bookmark::factory()->create();
    actingAs($u)->patch("/bookmarks/{$foreign->id}", ['title' => 'x'])->assertForbidden();
});

it('updates own bookmark and flashes updated', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $b = Bookmark::factory()->for($u)->create(['title' => 'old']);
    actingAs($u)->patch("/bookmarks/{$b->id}", ['title' => 'new'])
        ->assertRedirect()->assertSessionHas('flash.kind', 'updated');
    expect($b->fresh()->title)->toBe('new');
});

it('leaves folder untouched when folder_id is absent but moves to unfiled when null', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $f = Folder::factory()->for($u)->create();
    $b = Bookmark::factory()->for($u)->create(['folder_id' => $f->id]);
    actingAs($u);

    patch("/bookmarks/{$b->id}", ['title' => 'x']);
    expect($b->fresh()->folder_id)->toBe($f->id);

    patch("/bookmarks/{$b->id}", ['folder_id' => null]);
    expect($b->fresh()->folder_id)->toBeNull();
});

it('deletes own bookmark', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $b = Bookmark::factory()->for($u)->create();
    actingAs($u)->delete("/bookmarks/{$b->id}")->assertRedirect();
    $this->assertDatabaseMissing('bookmarks', ['id' => $b->id]);
});

it('forbids deleting another user\'s bookmark', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Bookmark::factory()->create();
    actingAs($u)->delete("/bookmarks/{$foreign->id}")->assertForbidden();
});

it('bulk-moves selected bookmarks into a folder', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $f = Folder::factory()->for($u)->create();
    $ids = Bookmark::factory()->for($u)->count(3)->create()->pluck('id')->all();
    actingAs($u)->post('/bookmarks/bulk', ['action' => 'move', 'ids' => $ids, 'folder_id' => $f->id])
        ->assertRedirect();
    expect(Bookmark::whereIn('id', $ids)->where('folder_id', $f->id)->count())->toBe(3);
});

it('bulk operations cannot touch another user\'s rows', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $f = Folder::factory()->for($u)->create();
    $foreign = Bookmark::factory()->create(['folder_id' => null]);
    actingAs($u)->post('/bookmarks/bulk', ['action' => 'move', 'ids' => [$foreign->id], 'folder_id' => $f->id])
        ->assertRedirect();
    expect($foreign->fresh()->folder_id)->toBeNull();
});

it('bulk-tags and untags selected bookmarks', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $ids = Bookmark::factory()->for($u)->count(2)->create()->pluck('id')->all();
    actingAs($u);

    post('/bookmarks/bulk', ['action' => 'tag', 'ids' => $ids, 'tag' => 'reading'])->assertRedirect();
    expect(Tag::where('user_id', $u->id)->where('name_lower', 'reading')->first()->bookmarks()->count())->toBe(2);

    post('/bookmarks/bulk', ['action' => 'untag', 'ids' => $ids, 'tag' => 'reading'])->assertRedirect();
    expect(Tag::where('user_id', $u->id)->where('name_lower', 'reading')->exists())->toBeFalse();
});

it('bulk-deletes selected bookmarks', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $ids = Bookmark::factory()->for($u)->count(3)->create()->pluck('id')->all();
    actingAs($u)->post('/bookmarks/bulk', ['action' => 'delete', 'ids' => $ids])->assertRedirect();
    expect(Bookmark::whereIn('id', $ids)->count())->toBe(0);
});

it('requires authentication for the index', function () {
    get('/')->assertRedirect('/login');
});
