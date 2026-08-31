<?php

use App\DataTransfer\BookmarkInput;
use App\Enums\MetadataStatus;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\TagService;
use App\Services\UrlNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->svc = new BookmarkService(new UrlNormalizer, new TagService);
});

it('creates a new bookmark and dispatches metadata when title is missing', function () {
    Queue::fake();
    $u = User::factory()->create();
    $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/x/']));
    expect($r['alreadySaved'])->toBeFalse()
        ->and($r['bookmark']->normalized_url)->toBe('https://a.test/x')
        ->and($r['bookmark']->metadata_status)->toBe(MetadataStatus::Pending);
    Queue::assertPushed(FetchBookmarkMetadata::class);
});

it('is idempotent on the normalized URL', function () {
    Queue::fake();
    $u = User::factory()->create();
    $a = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/p?utm_source=x']));
    $b = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/p', 'title' => 'ignored']));
    expect($b['alreadySaved'])->toBeTrue()
        ->and($b['bookmark']->id)->toBe($a['bookmark']->id)
        ->and($b['bookmark']->title)->toBeNull();
    expect(Bookmark::where('user_id', $u->id)->count())->toBe(1);
});

it('marks status Done when a title is supplied and metadata enabled', function () {
    Queue::fake();
    $u = User::factory()->create();
    $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/', 'title' => 'Hi']));
    expect($r['bookmark']->metadata_status)->toBe(MetadataStatus::Done);
    Queue::assertNotPushed(FetchBookmarkMetadata::class);
});

it('marks status Skipped when metadata fetching is disabled', function () {
    config()->set('mahalinkam.metadata.enabled', false);
    $u = User::factory()->create();
    $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/']));
    expect($r['bookmark']->metadata_status)->toBe(MetadataStatus::Skipped);
});

it('rejects a folder that belongs to another user', function () {
    $u = User::factory()->create();
    $other = Folder::factory()->create();
    expect(fn () => $this->svc->save($u, BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'folderId' => $other->id,
    ])))->toThrow(ModelNotFoundException::class);
});

it('deletes a bookmark and prunes its now-orphan tags', function () {
    $u = User::factory()->create();
    $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/', 'title' => 't', 'tags' => ['x']]));
    $this->svc->delete($r['bookmark']);
    expect(Bookmark::count())->toBe(0)
        ->and(Tag::where('user_id', $u->id)->count())->toBe(0);
});

it('moves a bookmark to Unfiled via folder_id => null', function () {
    Queue::fake();
    $u = User::factory()->create();
    $folder = $u->folders()->create(['name' => 'F', 'position' => 1]);
    $r = $this->svc->save($u, BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'title' => 't', 'folder_id' => $folder->id,
    ]));
    expect($r['bookmark']->folder_id)->toBe($folder->id);

    $updated = $this->svc->update($r['bookmark'], BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'folder_id' => null,
    ]));
    expect($updated->folder_id)->toBeNull();
});

it('leaves folder untouched on update when folder_id is not provided', function () {
    Queue::fake();
    $u = User::factory()->create();
    $folder = $u->folders()->create(['name' => 'F', 'position' => 1]);
    $r = $this->svc->save($u, BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'title' => 't', 'folder_id' => $folder->id,
    ]));

    $updated = $this->svc->update($r['bookmark'], BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'title' => 'new title',
    ]));
    expect($updated->folder_id)->toBe($folder->id)
        ->and($updated->title)->toBe('new title');
});
