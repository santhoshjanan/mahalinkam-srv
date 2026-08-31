<?php

namespace App\Http\Resources;

use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bookmark
 *
 * Callers must eager-load the `tags` relation (e.g. `->load('tags')` or
 * `with('tags')`) before serializing so the tag list resolves without an
 * N+1 query. Task 24 controllers own that eager-load.
 */
class BookmarkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'normalized_url' => $this->normalized_url,
            'title' => $this->title,
            'description' => $this->description,
            'favicon_url' => $this->favicon_url,
            'folder_id' => $this->folder_id,
            'tags' => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->values(),
            'metadata_status' => $this->metadata_status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
