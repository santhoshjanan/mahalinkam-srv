<?php

namespace App\Http\Controllers\Web;

use App\Enums\MetadataStatus;
use App\Http\Controllers\Controller;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use Illuminate\Http\RedirectResponse;

class RefetchMetadataController extends Controller
{
    public function __invoke(Bookmark $bookmark): RedirectResponse
    {
        $this->authorize('update', $bookmark);

        $bookmark->update(['metadata_status' => MetadataStatus::Pending]);
        FetchBookmarkMetadata::dispatch($bookmark);

        return back();
    }
}
