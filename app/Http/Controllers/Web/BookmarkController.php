<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\InvalidUrlException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookmarkRequest;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Models\Bookmark;
use App\Queries\BookmarkListQuery;
use App\Services\BookmarkService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookmarkController extends Controller
{
    public function __construct(private BookmarkService $service) {}

    public function index(): Response
    {
        $user = request()->user();
        $filters = request()->only(['q', 'folder_id', 'tags', 'sort', 'page']);

        return Inertia::render('Bookmarks/Index', [
            'bookmarks' => BookmarkListQuery::for($user, $filters)
                ->through(fn (Bookmark $b) => $this->present($b)),
            'folders' => $user->folders()->orderBy('position')->get(['id', 'parent_id', 'name', 'position']),
            'tags' => $user->tags()->withCount('bookmarks')->orderBy('name_lower')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function store(StoreBookmarkRequest $request): RedirectResponse
    {
        try {
            $r = $this->service->save($request->user(), $request->toBookmarkInput());
        } catch (InvalidUrlException $e) {
            return back()->withErrors(['url' => $e->getMessage()]);
        }

        return back()->with('flash', [
            'kind' => $r['alreadySaved'] ? 'already_saved' : 'saved',
            'id' => $r['bookmark']->id,
        ]);
    }

    public function update(UpdateBookmarkRequest $request, Bookmark $bookmark): RedirectResponse
    {
        $this->authorize('update', $bookmark);

        try {
            $this->service->update($bookmark, $request->toBookmarkInput());
        } catch (InvalidUrlException $e) {
            return back()->withErrors(['url' => $e->getMessage()]);
        }

        return back()->with('flash', ['kind' => 'updated']);
    }

    public function destroy(Bookmark $bookmark): RedirectResponse
    {
        $this->authorize('delete', $bookmark);

        $this->service->delete($bookmark);

        return back()->with('flash', ['kind' => 'deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Bookmark $b): array
    {
        return [
            'id' => $b->id,
            'url' => $b->url,
            'normalized_url' => $b->normalized_url,
            'title' => $b->title,
            'description' => $b->description,
            'favicon_url' => $b->favicon_url,
            'folder_id' => $b->folder_id,
            'metadata_status' => $b->metadata_status->value,
            'tags' => $b->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            'created_at' => $b->created_at->toIso8601String(),
        ];
    }
}
