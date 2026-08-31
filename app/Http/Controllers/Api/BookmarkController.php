<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidUrlException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookmarkRequest;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Http\Resources\BookmarkResource;
use App\Queries\BookmarkListQuery;
use App\Services\BookmarkService;
use App\Services\UrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookmarkController extends Controller
{
    public function __construct(
        private BookmarkService $bookmarks,
        private UrlNormalizer $normalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = BookmarkListQuery::for($request->user(), [
            'q' => $request->query('q'),
            'folder_id' => $request->query('folder_id'),
            'tags' => (array) $request->query('tag', []),
            'sort' => $request->query('sort'),
            'page' => $request->query('page'),
        ]);

        return BookmarkResource::collection($paginator)->response();
    }

    public function store(StoreBookmarkRequest $request): JsonResponse
    {
        try {
            $result = $this->bookmarks->save($request->user(), $request->toBookmarkInput());
        } catch (InvalidUrlException $e) {
            abort(422, $e->getMessage());
        }

        return (new BookmarkResource($result['bookmark']->loadMissing('tags')))
            ->additional(['already_saved' => $result['alreadySaved']])
            ->response()
            ->setStatusCode($result['alreadySaved'] ? 200 : 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'string']]);

        try {
            $normalized = $this->normalizer->normalize($validated['url']);
        } catch (InvalidUrlException $e) {
            abort(422, $e->getMessage());
        }

        $bookmark = $request->user()->bookmarks()
            ->with('tags')
            ->where('normalized_url', $normalized)
            ->first();

        return response()->json([
            'found' => (bool) $bookmark,
            'bookmark' => $bookmark ? new BookmarkResource($bookmark) : null,
        ]);
    }

    public function update(UpdateBookmarkRequest $request, int $bookmark): BookmarkResource
    {
        $model = $request->user()->bookmarks()->findOrFail($bookmark);

        try {
            $updated = $this->bookmarks->update($model, $request->toBookmarkInput());
        } catch (InvalidUrlException $e) {
            abort(422, $e->getMessage());
        }

        return new BookmarkResource($updated->fresh('tags'));
    }

    public function destroy(Request $request, int $bookmark): Response
    {
        $model = $request->user()->bookmarks()->findOrFail($bookmark);

        $this->bookmarks->delete($model);

        return response()->noContent();
    }
}
