<?php

use App\Enums\MetadataStatus;
use App\Exceptions\BlockedHostException;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use App\Models\User;
use App\Services\MetadataFetcher;
use Illuminate\Support\Facades\Queue;

function fakeFetcherReturning(array $meta): void
{
    $mock = Mockery::mock(MetadataFetcher::class);
    $mock->shouldReceive('fetch')->andReturn($meta);
    app()->instance(MetadataFetcher::class, $mock);
}

it('fills only empty fields on success', function () {
    fakeFetcherReturning([
        'title' => 'Real Title',
        'description' => 'fetched description',
        'favicon_url' => 'https://example.com/fav.png',
    ]);

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'url' => 'https://example.com/page',
        'normalized_url' => 'https://example.com/page',
        'title' => null,
        'description' => 'user wrote this',
        'favicon_url' => null,
        'metadata_status' => MetadataStatus::Pending,
    ]);

    (new FetchBookmarkMetadata($b))->handle(app(MetadataFetcher::class));

    $b->refresh();
    expect($b->title)->toBe('Real Title')
        ->and($b->description)->toBe('user wrote this') // untouched
        ->and($b->favicon_url)->toBe('https://example.com/fav.png')
        ->and($b->metadata_status)->toBe(MetadataStatus::Done);
});

it('does not run for a bookmark that is not Pending or Failed', function () {
    fakeFetcherReturning([
        'title' => 'Should Not Apply',
        'description' => null,
        'favicon_url' => null,
    ]);

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'title' => 'kept',
        'metadata_status' => MetadataStatus::Done,
    ]);

    (new FetchBookmarkMetadata($b))->handle(app(MetadataFetcher::class));

    expect($b->fresh()->title)->toBe('kept');
});

it('re-runs for a Failed bookmark', function () {
    fakeFetcherReturning([
        'title' => 'Recovered Title',
        'description' => null,
        'favicon_url' => null,
    ]);

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'title' => null,
        'metadata_status' => MetadataStatus::Failed,
    ]);

    (new FetchBookmarkMetadata($b))->handle(app(MetadataFetcher::class));

    $b->refresh();
    expect($b->title)->toBe('Recovered Title')
        ->and($b->metadata_status)->toBe(MetadataStatus::Done);
});

it('marks the bookmark Failed via the failed() hook', function () {
    $mock = Mockery::mock(MetadataFetcher::class);
    $mock->shouldReceive('fetch')->andThrow(new BlockedHostException('blocked'));
    app()->instance(MetadataFetcher::class, $mock);

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'url' => 'http://127.0.0.1/x',
        'normalized_url' => 'http://127.0.0.1/x',
        'metadata_status' => MetadataStatus::Pending,
        'title' => null,
    ]);

    $job = new FetchBookmarkMetadata($b);
    try {
        $job->handle(app(MetadataFetcher::class));
    } catch (Throwable) {
        // expected on the final attempt
    }
    $job->failed(new RuntimeException('blocked'));

    expect($b->fresh()->metadata_status)->toBe(MetadataStatus::Failed);
});

it('failed() does not clobber a Done status', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'metadata_status' => MetadataStatus::Done,
    ]);

    (new FetchBookmarkMetadata($b))->failed(new RuntimeException('late failure'));

    expect($b->fresh()->metadata_status)->toBe(MetadataStatus::Done);
});

it('is a no-op when metadata fetching is disabled', function () {
    config(['mahalinkam.metadata.enabled' => false]);

    $mock = Mockery::mock(MetadataFetcher::class);
    $mock->shouldNotReceive('fetch');
    app()->instance(MetadataFetcher::class, $mock);

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'title' => null,
        'metadata_status' => MetadataStatus::Pending,
    ]);

    (new FetchBookmarkMetadata($b))->handle(app(MetadataFetcher::class));

    $b->refresh();
    expect($b->title)->toBeNull()
        ->and($b->metadata_status)->toBe(MetadataStatus::Pending);
});

it('re-queues a fetch via the refetch endpoint for the owner', function () {
    Queue::fake();

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'metadata_status' => MetadataStatus::Failed,
    ]);

    $this->actingAs($u)
        ->post("/bookmarks/{$b->id}/refetch")
        ->assertRedirect();

    expect($b->fresh()->metadata_status)->toBe(MetadataStatus::Pending);
    Queue::assertPushed(FetchBookmarkMetadata::class);
});

it('forbids a stranger from triggering a refetch', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $b = Bookmark::factory()->for($owner)->create([
        'metadata_status' => MetadataStatus::Failed,
    ]);

    $this->actingAs($stranger)
        ->post("/bookmarks/{$b->id}/refetch")
        ->assertForbidden();

    expect($b->fresh()->metadata_status)->toBe(MetadataStatus::Failed);
    Queue::assertNothingPushed();
});
