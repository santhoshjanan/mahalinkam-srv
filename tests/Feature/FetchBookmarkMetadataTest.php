<?php

use App\Enums\MetadataStatus;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use App\Models\User;
use App\Services\MetadataFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('fills only empty fields on success', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>Real Title</title>'
        .'<meta property="og:description" content="Desc">'
        .'<link rel="icon" href="/fav.png"></head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

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

it('marks failed when the host resolves to a private IP', function () {
    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'url' => 'http://127.0.0.1/x',
        'normalized_url' => 'http://127.0.0.1/x',
        'metadata_status' => MetadataStatus::Pending,
        'title' => null,
    ]);

    $job = new FetchBookmarkMetadata($b);
    // simulate the final attempt
    try {
        $job->handle(app(MetadataFetcher::class));
    } catch (Throwable) {
        // expected
    }
    $job->failed(new RuntimeException('blocked'));

    expect($b->fresh()->metadata_status)->toBe(MetadataStatus::Failed);
});

it('is a no-op when metadata fetching is disabled', function () {
    config(['mahalinkam.metadata.enabled' => false]);

    Http::fake(); // any request would be a failure

    $u = User::factory()->create();
    $b = Bookmark::factory()->for($u)->create([
        'url' => 'https://example.com/page',
        'normalized_url' => 'https://example.com/page',
        'title' => null,
        'metadata_status' => MetadataStatus::Pending,
    ]);

    (new FetchBookmarkMetadata($b))->handle(app(MetadataFetcher::class));

    $b->refresh();
    expect($b->title)->toBeNull()
        ->and($b->metadata_status)->toBe(MetadataStatus::Pending);

    Http::assertNothingSent();
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
