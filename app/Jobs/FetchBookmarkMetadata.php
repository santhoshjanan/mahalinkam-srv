<?php

namespace App\Jobs;

use App\Enums\MetadataStatus;
use App\Models\Bookmark;
use App\Services\MetadataFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchBookmarkMetadata implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 300];

    public function __construct(public Bookmark $bookmark) {}

    public function handle(MetadataFetcher $fetcher): void
    {
        if (! config('mahalinkam.metadata.enabled')) {
            return;
        }

        $bookmark = $this->bookmark->fresh();

        if ($bookmark === null) {
            return;
        }

        if (! in_array($bookmark->metadata_status, [MetadataStatus::Pending, MetadataStatus::Failed], true)) {
            return;
        }

        $meta = $fetcher->fetch($bookmark->url);

        // Fill only fields the user left empty; never overwrite user-provided values.
        $bookmark->title ??= $meta['title'];
        $bookmark->description ??= $meta['description'];
        $bookmark->favicon_url ??= $meta['favicon_url'];
        $bookmark->metadata_status = MetadataStatus::Done;
        $bookmark->save();
    }

    public function failed(\Throwable $e): void
    {
        $bookmark = $this->bookmark->fresh();

        if ($bookmark !== null && $bookmark->metadata_status !== MetadataStatus::Done) {
            $bookmark->update(['metadata_status' => MetadataStatus::Failed]);
        }
    }
}
