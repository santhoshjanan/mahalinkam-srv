<?php

use App\Enums\MetadataStatus;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;

it('wires user -> folders/bookmarks/tags', function () {
    $u = User::factory()->create();
    Folder::factory()->for($u)->create();
    Bookmark::factory()->for($u)->create();
    Tag::factory()->for($u)->create();

    expect($u->folders)->toHaveCount(1)
        ->and($u->bookmarks)->toHaveCount(1)
        ->and($u->tags)->toHaveCount(1);
});

it('nests folders and attaches tags to bookmarks', function () {
    $u = User::factory()->create();
    $root = Folder::factory()->for($u)->create();
    $child = Folder::factory()->for($u)->create(['parent_id' => $root->id]);

    expect($child->parent->is($root))->toBeTrue()
        ->and($root->children->first()->is($child))->toBeTrue();

    $b = Bookmark::factory()->for($u)->create();
    $t = Tag::factory()->for($u)->create();
    $b->tags()->attach($t);

    expect($b->fresh()->tags)->toHaveCount(1);
});

it('casts metadata_status to the enum', function () {
    $b = Bookmark::factory()->create(['metadata_status' => 'pending']);

    expect($b->metadata_status)->toBe(MetadataStatus::Pending);
});
