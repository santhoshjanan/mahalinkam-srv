<?php

use App\DataTransfer\BookmarkInput;
use App\Enums\MetadataStatus;
use App\Exceptions\InvalidUrlException;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\TagService;
use App\Services\UrlNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->svc = new BookmarkService(new UrlNormalizer, new TagService);
});

/**
 * Build a Throwable whose getCode() returns the given SQLSTATE string,
 * suitable as the "previous" for a synthetic QueryException.
 */
function sqlstate(string $code): Throwable
{
    return new class($code) extends RuntimeException
    {
        public function __construct(string $code)
        {
            parent::__construct("SQLSTATE[$code]");
            $this->code = $code;
        }
    };
}

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
    Queue::fake();
    config()->set('mahalinkam.metadata.enabled', false);
    $u = User::factory()->create();
    $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/']));
    expect($r['bookmark']->metadata_status)->toBe(MetadataStatus::Skipped);
});

it('rejects a folder that belongs to another user', function () {
    Queue::fake();
    $u = User::factory()->create();
    $other = Folder::factory()->create();
    expect(fn () => $this->svc->save($u, BookmarkInput::fromArray([
        'url' => 'https://a.test/', 'folderId' => $other->id,
    ])))->toThrow(ModelNotFoundException::class);
});

it('deletes a bookmark and prunes its now-orphan tags', function () {
    Queue::fake();
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

it('rejects an update whose new URL collides with another bookmark of the same user', function () {
    Queue::fake();
    $u = User::factory()->create();
    $first = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/one', 'title' => 'one']));
    $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://a.test/two', 'title' => 'two']));

    expect(fn () => $this->svc->update($first['bookmark'], BookmarkInput::fromArray([
        'url' => 'https://a.test/two',
    ])))->toThrow(InvalidUrlException::class, 'This URL is already saved.');
});

it('recovers from a unique-violation race by re-selecting the existing row', function () {
    Queue::fake();
    $u = User::factory()->create();

    // The `creating` hook simulates a concurrent request that wins the INSERT
    // after save()'s pre-check passes: it writes the real row (bypassing model
    // events via the query builder) and then raises SQLSTATE 23000, exactly as
    // the DB would for our losing INSERT.
    $normalized = (new UrlNormalizer)->normalize('https://race.test/');

    $fired = false;
    Bookmark::creating(function () use (&$fired, $u, $normalized) {
        if ($fired) {
            return;
        }
        $fired = true;
        DB::table('bookmarks')->insert([
            'user_id' => $u->id,
            'url' => 'https://race.test/',
            'normalized_url' => $normalized,
            'metadata_status' => MetadataStatus::Pending->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        throw new QueryException('mysql', 'insert', [], sqlstate('23000'));
    });

    try {
        $r = $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://race.test/']));
    } finally {
        Bookmark::flushEventListeners();
    }

    expect($r['alreadySaved'])->toBeTrue()
        ->and($r['bookmark']->normalized_url)->toBe($normalized)
        ->and(Bookmark::where('user_id', $u->id)->count())->toBe(1);
});

it('propagates a non-unique QueryException out of save()', function () {
    Queue::fake();
    $u = User::factory()->create();

    $fired = false;
    Bookmark::creating(function () use (&$fired) {
        if ($fired) {
            return;
        }
        $fired = true;
        throw new QueryException('mysql', 'insert', [], sqlstate('42S22'));
    });

    try {
        expect(fn () => $this->svc->save($u, BookmarkInput::fromArray(['url' => 'https://boom.test/'])))
            ->toThrow(QueryException::class);
    } finally {
        Bookmark::flushEventListeners();
    }
});
