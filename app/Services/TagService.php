<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

class TagService
{
    public function syncForBookmark(Bookmark $bookmark, array $names): void
    {
        $user = $bookmark->user;

        $clean = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => Str::lower($n))
            ->values();

        $ids = $clean->map(function (string $name) use ($user) {
            return $user->tags()->firstOrCreate(
                ['name_lower' => Str::lower($name)],
                ['name' => $name],
            )->id;
        })->all();

        $bookmark->tags()->sync($ids);
        $this->pruneOrphans($user);
    }

    public function pruneOrphans(User $user): int
    {
        return Tag::where('user_id', $user->id)->doesntHave('bookmarks')->delete();
    }
}
