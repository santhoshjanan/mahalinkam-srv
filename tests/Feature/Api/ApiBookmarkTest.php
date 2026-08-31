<?php

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function tok(User $u): string
{
    return $u->createToken('t')->plainTextToken;
}

it('creates then dedupes via POST', function () {
    $u = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.tok($u)];

    $this->withHeaders($h)->postJson('/api/bookmarks', ['url' => 'https://a.test/p?utm_source=x'])
        ->assertCreated()
        ->assertJsonPath('already_saved', false)
        ->assertJsonPath('data.normalized_url', 'https://a.test/p');

    $this->withHeaders($h)->postJson('/api/bookmarks', ['url' => 'https://a.test/p'])
        ->assertOk()
        ->assertJsonPath('already_saved', true);

    expect(Bookmark::where('user_id', $u->id)->count())->toBe(1);
});

it('lookup matches on the normalized url', function () {
    $u = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.tok($u)];
    $this->withHeaders($h)->postJson('/api/bookmarks', ['url' => 'https://a.test/p']);

    $this->withHeaders($h)->getJson('/api/bookmarks/lookup?url='.urlencode('https://a.test/p#frag'))
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('bookmark.normalized_url', 'https://a.test/p');

    $this->withHeaders($h)->getJson('/api/bookmarks/lookup?url='.urlencode('https://a.test/other'))
        ->assertOk()
        ->assertJsonPath('found', false)
        ->assertJsonPath('bookmark', null);
});

it('lookup with a missing url is a 422', function () {
    $u = User::factory()->create();
    $this->withHeaders(['Authorization' => 'Bearer '.tok($u)])
        ->getJson('/api/bookmarks/lookup')
        ->assertStatus(422);
});

it('isolates users across read/update/delete', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $b = Bookmark::factory()->for($owner)->create();
    $h = ['Authorization' => 'Bearer '.tok($stranger)];

    $this->withHeaders($h)->getJson('/api/bookmarks?folder_id=&page=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withHeaders($h)->patchJson("/api/bookmarks/{$b->id}", ['title' => 'x'])
        ->assertNotFound();

    $this->withHeaders($h)->deleteJson("/api/bookmarks/{$b->id}")
        ->assertNotFound();
});

it('paginates with page-number meta', function () {
    $u = User::factory()->create();
    Bookmark::factory()->for($u)->count(60)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.tok($u)])
        ->getJson('/api/bookmarks?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonCount(10, 'data');
});

it('updates an owned bookmark', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create(['title' => 'old title']);

    $this->withHeaders(['Authorization' => 'Bearer '.tok($u)])
        ->patchJson("/api/bookmarks/{$b->id}", ['title' => 'new title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'new title');

    expect($b->fresh()->title)->toBe('new title');
});

it('deletes an owned bookmark', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.tok($u)])
        ->deleteJson("/api/bookmarks/{$b->id}")
        ->assertNoContent();

    expect(Bookmark::find($b->id))->toBeNull();
});

it('rejects an invalid url on POST with a 422', function () {
    $u = User::factory()->create();
    $this->withHeaders(['Authorization' => 'Bearer '.tok($u)])
        ->postJson('/api/bookmarks', ['url' => 'not a real url'])
        ->assertStatus(422);
});

it('narrows the list by the tag[] filter', function () {
    $u = User::factory()->create();
    $h = ['Authorization' => 'Bearer '.tok($u)];

    $this->withHeaders($h)->postJson('/api/bookmarks', ['url' => 'https://x.test/1', 'tags' => ['work']])
        ->assertCreated();
    $this->withHeaders($h)->postJson('/api/bookmarks', ['url' => 'https://x.test/2', 'tags' => ['home']])
        ->assertCreated();

    $this->withHeaders($h)->getJson('/api/bookmarks?tag[]=work')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', 'https://x.test/1');
});
