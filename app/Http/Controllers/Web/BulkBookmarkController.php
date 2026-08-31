<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\User;
use App\Rules\OwnedFolder;
use App\Services\BookmarkService;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BulkBookmarkController extends Controller
{
    public function __construct(
        private BookmarkService $bookmarks,
        private TagService $tags,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'action' => ['required', 'string', 'in:move,tag,untag,delete'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', new OwnedFolder($user->id)],
            'tag' => ['nullable', 'string', 'max:80'],
        ]);

        /** @var Collection<int, Bookmark> $rows */
        $rows = Bookmark::whereIn('id', $data['ids'])
            ->where('user_id', $user->id)
            ->get();

        match ($data['action']) {
            'move' => $this->move($rows, $data['folder_id'] ?? null),
            'tag' => $this->tag($user, $rows, (string) ($data['tag'] ?? '')),
            'untag' => $this->untag($user, $rows, (string) ($data['tag'] ?? '')),
            'delete' => $this->delete($rows),
        };

        return back()->with('flash', ['kind' => 'updated', 'count' => $rows->count()]);
    }

    /**
     * @param  Collection<int, Bookmark>  $rows
     */
    private function move($rows, ?int $folderId): void
    {
        foreach ($rows as $bookmark) {
            $bookmark->update(['folder_id' => $folderId]);
        }
    }

    /**
     * @param  Collection<int, Bookmark>  $rows
     */
    private function tag(User $user, $rows, string $name): void
    {
        $name = trim($name);
        if ($name === '' || $rows->isEmpty()) {
            return;
        }

        $tag = $user->tags()->firstOrCreate(
            ['name_lower' => Str::lower($name)],
            ['name' => $name],
        );

        foreach ($rows as $bookmark) {
            $bookmark->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    /**
     * @param  Collection<int, Bookmark>  $rows
     */
    private function untag(User $user, $rows, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $tag = $user->tags()->where('name_lower', Str::lower($name))->first();
        if (! $tag) {
            return;
        }

        foreach ($rows as $bookmark) {
            $bookmark->tags()->detach($tag->id);
        }

        $this->tags->pruneOrphans($user);
    }

    /**
     * @param  Collection<int, Bookmark>  $rows
     */
    private function delete($rows): void
    {
        foreach ($rows as $bookmark) {
            $this->bookmarks->delete($bookmark);
        }
    }
}
