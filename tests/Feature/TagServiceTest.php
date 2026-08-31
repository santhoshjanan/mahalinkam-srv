<?php

use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;

beforeEach(fn () => $this->svc = new TagService);

it('creates, dedupes case-insensitively, and trims tags', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create();
    $this->svc->syncForBookmark($b, [' News ', 'news', 'Tech', '']);
    expect($b->fresh()->tags->pluck('name_lower')->sort()->values()->all())->toBe(['news', 'tech'])
        ->and(Tag::where('user_id', $u->id)->count())->toBe(2);
});

it('prunes tags left with no bookmarks after a re-sync', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create();
    $this->svc->syncForBookmark($b, ['keep', 'drop']);
    $this->svc->syncForBookmark($b, ['keep']);
    expect(Tag::where('user_id', $u->id)->pluck('name_lower')->all())->toBe(['keep']);
});

it('does not prune a tag still used by another bookmark', function () {
    $u = User::factory()->create();
    [$b1, $b2] = Bookmark::factory()->for($u)->count(2)->create();
    $this->svc->syncForBookmark($b1, ['shared']);
    $this->svc->syncForBookmark($b2, ['shared']);
    $this->svc->syncForBookmark($b1, []);
    expect(Tag::where('user_id', $u->id)->count())->toBe(1);
});

it('prunes orphans only for the given user and returns the count', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $b2 = Bookmark::factory()->for($u2)->create();

    // u1 has two orphan tags, u2 has one attached tag plus one orphan.
    Tag::factory()->for($u1)->create(['name' => 'a', 'name_lower' => 'a']);
    Tag::factory()->for($u1)->create(['name' => 'b', 'name_lower' => 'b']);
    $this->svc->syncForBookmark($b2, ['live']);
    Tag::factory()->for($u2)->create(['name' => 'stale', 'name_lower' => 'stale']);

    $deleted = $this->svc->pruneOrphans($u1);

    expect($deleted)->toBe(2)
        ->and(Tag::where('user_id', $u1->id)->count())->toBe(0)
        ->and(Tag::where('user_id', $u2->id)->count())->toBe(2);
});
