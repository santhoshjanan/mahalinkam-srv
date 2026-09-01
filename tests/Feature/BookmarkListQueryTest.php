<?php

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use App\Queries\BookmarkListQuery;
use App\Services\TagService;

function bm(User $u, array $attrs = [], array $tags = []): Bookmark
{
    $b = Bookmark::factory()->for($u)->create($attrs);
    if ($tags) {
        app(TagService::class)->syncForBookmark($b, $tags);
    }

    return $b->fresh();
}

it('filters by q across title, url, description, and tag name', function () {
    $u = User::factory()->create();
    bm($u, ['title' => 'Rust guide', 'url' => 'https://x.test/a', 'description' => null]);
    bm($u, ['title' => 'Cooking', 'url' => 'https://rust-lang.org/', 'description' => null]);
    bm($u, ['title' => 'Misc', 'url' => 'https://y.test/', 'description' => 'about rust internals']);
    bm($u, ['title' => 'Tagged', 'url' => 'https://z.test/'], ['rust']);
    bm($u, ['title' => 'Nope', 'url' => 'https://n.test/']);

    $res = BookmarkListQuery::for($u, ['q' => 'rust']);
    expect($res->total())->toBe(4);
});

it('ANDs multiple q terms', function () {
    $u = User::factory()->create();
    bm($u, ['title' => 'Rust async book', 'url' => 'https://x.test/']);
    bm($u, ['title' => 'Rust sync', 'url' => 'https://y.test/']);
    expect(BookmarkListQuery::for($u, ['q' => 'rust async'])->total())->toBe(1);
});

it('filters unfiled vs a specific folder (non-recursive)', function () {
    $u = User::factory()->create();
    $parent = Folder::factory()->for($u)->create();
    $child = Folder::factory()->for($u)->create(['parent_id' => $parent->id]);
    bm($u, ['folder_id' => null]);
    bm($u, ['folder_id' => $parent->id]);
    bm($u, ['folder_id' => $child->id]);

    expect(BookmarkListQuery::for($u, ['folder_id' => 'unfiled'])->total())->toBe(1)
        ->and(BookmarkListQuery::for($u, ['folder_id' => (string) $parent->id])->total())->toBe(1);
});

it('ANDs tag filters and sorts', function () {
    $u = User::factory()->create();
    // Titles must differ at a LETTER, not at whitespace/punctuation: glibc's
    // en_US.UTF-8 collation (Postgres CI) treats a space as ignorable at the
    // primary level, so "A only" would sort AFTER "AB" there while SQLite/MySQL
    // (byte order) put it first. `Alpha` < `Beta` < `zzz` in every collation.
    bm($u, ['title' => 'Beta'], ['a', 'b']);
    bm($u, ['title' => 'Alpha'], ['a']);
    expect(BookmarkListQuery::for($u, ['tags' => ['a', 'b']])->total())->toBe(1);

    bm($u, ['title' => 'zzz']);
    $titles = BookmarkListQuery::for($u, ['sort' => 'title_asc'])->pluck('title')->all();
    expect($titles)->toBe(['Alpha', 'Beta', 'zzz']);
});

it('sorts created_desc by default and created_asc as the reverse', function () {
    $u = User::factory()->create();
    $ids = [];
    for ($i = 0; $i < 3; $i++) {
        $ids[] = Bookmark::factory()->for($u)->create(['created_at' => now()->subMinutes($i)])->id;
    }
    // $ids[0] is newest, $ids[2] is oldest.

    expect(BookmarkListQuery::for($u, [])->pluck('id')->all())->toBe($ids)
        ->and(BookmarkListQuery::for($u, ['sort' => 'created_asc'])->pluck('id')->all())
        ->toBe(array_reverse($ids));
});

it('is deterministic for equal created_at, breaking ties by id desc', function () {
    $u = User::factory()->create();
    $ts = now();
    $a = Bookmark::factory()->for($u)->create(['created_at' => $ts]);
    $b = Bookmark::factory()->for($u)->create(['created_at' => $ts]);

    $first = BookmarkListQuery::for($u, [])->pluck('id')->all();
    $second = BookmarkListQuery::for($u, [])->pluck('id')->all();

    expect($first)->toBe($second)
        ->and($first)->toBe([$b->id, $a->id]);
});

it('scopes to the user and paginates 50', function () {
    $u = User::factory()->create();
    Bookmark::factory()->for($u)->count(55)->create();
    Bookmark::factory()->count(3)->create(); // other users
    $res = BookmarkListQuery::for($u, []);
    expect($res->total())->toBe(55)->and($res->perPage())->toBe(50);
});
