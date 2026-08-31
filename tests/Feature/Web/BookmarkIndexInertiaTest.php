<?php

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use App\Services\TagService;

use function Pest\Laravel\actingAs;

it('provides the full prop shape the page needs', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $f = Folder::factory()->for($u)->create();
    $b = Bookmark::factory()->for($u)->create(['folder_id' => $f->id]);
    app(TagService::class)->syncForBookmark($b, ['alpha']);

    actingAs($u)->get('/?q=&sort=title_asc')->assertInertia(fn ($p) => $p
        ->component('Bookmarks/Index')
        ->has('bookmarks.data.0', fn ($row) => $row
            ->hasAll(['id', 'url', 'normalized_url', 'title', 'description', 'favicon_url', 'folder_id', 'metadata_status', 'tags', 'created_at'])
            ->etc())
        ->has('folders.0', fn ($row) => $row->hasAll(['id', 'parent_id', 'name', 'position'])->etc())
        ->has('tags.0', fn ($row) => $row->hasAll(['id', 'name'])->etc())
        ->where('filters.sort', 'title_asc'));
});
