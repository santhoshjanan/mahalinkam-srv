<?php

namespace App\Services;

use App\DataTransfer\BookmarkInput;
use App\Enums\MetadataStatus;
use App\Exceptions\InvalidUrlException;
use App\Jobs\FetchBookmarkMetadata;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\QueryException;

class BookmarkService
{
    public function __construct(
        private UrlNormalizer $normalizer,
        private TagService $tags,
    ) {}

    /**
     * @return array{bookmark: Bookmark, alreadySaved: bool}
     */
    public function save(User $user, BookmarkInput $in): array
    {
        if ($in->folderId !== null) {
            $user->folders()->findOrFail($in->folderId);
        }

        $normalized = $this->normalizer->normalize($in->url);

        $existing = $user->bookmarks()->where('normalized_url', $normalized)->first();
        if ($existing) {
            return ['bookmark' => $existing, 'alreadySaved' => true];
        }

        $status = match (true) {
            ! config('mahalinkam.metadata.enabled') => MetadataStatus::Skipped,
            filled($in->title) => MetadataStatus::Done,
            default => MetadataStatus::Pending,
        };

        try {
            $bookmark = $user->bookmarks()->create([
                'folder_id' => $in->folderId,
                'url' => $in->url,
                'normalized_url' => $normalized,
                'title' => $this->clamp($in->title, 1024),
                'description' => $this->clamp($in->description, 5000),
                'metadata_status' => $status,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                // Re-select keyed only on normalized_url because (user_id, normalized_url)
                // is the sole unique index that can raise this SQLSTATE; if another unique
                // constraint is ever added to bookmarks, this recovery needs revisiting.
                return [
                    'bookmark' => $user->bookmarks()->where('normalized_url', $normalized)->firstOrFail(),
                    'alreadySaved' => true,
                ];
            }
            throw $e;
        }

        $this->tags->syncForBookmark($bookmark, $in->tags);

        if ($status === MetadataStatus::Pending) {
            FetchBookmarkMetadata::dispatch($bookmark);
        }

        return ['bookmark' => $bookmark->fresh('tags'), 'alreadySaved' => false];
    }

    public function update(Bookmark $bookmark, BookmarkInput $in): Bookmark
    {
        if (filled($in->url) && $in->url !== $bookmark->url) {
            $normalized = $this->normalizer->normalize($in->url);
            $clash = $bookmark->user->bookmarks()
                ->where('normalized_url', $normalized)
                ->whereKeyNot($bookmark->id)
                ->exists();
            if ($clash) {
                throw new InvalidUrlException('This URL is already saved.');
            }
            $bookmark->url = $in->url;
            $bookmark->normalized_url = $normalized;
        }

        if ($in->title !== null) {
            $bookmark->title = $this->clamp($in->title, 1024);
        }
        if ($in->description !== null) {
            $bookmark->description = $this->clamp($in->description, 5000);
        }

        if ($in->folderIdProvided) {
            if ($in->folderId !== null) {
                $bookmark->user->folders()->findOrFail($in->folderId);
            }
            $bookmark->folder_id = $in->folderId;
        }

        $bookmark->save();

        if ($in->tagsProvided) {
            $this->tags->syncForBookmark($bookmark, $in->tags);
        }

        return $bookmark->fresh('tags');
    }

    public function delete(Bookmark $bookmark): void
    {
        $user = $bookmark->user;
        $bookmark->delete();
        $this->tags->pruneOrphans($user);
    }

    /**
     * Clamp a user-supplied string to the width of its target column. SQLite
     * truncates silently but MySQL/Postgres raise a data-truncation error, so
     * imports and metadata writes must clamp at this shared choke point.
     */
    private function clamp(?string $v, int $max): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $max);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
