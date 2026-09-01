<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $tags = $request->user()->tags()
            ->withCount('bookmarks')
            // lower() is a deliberate ANSI-SQL exception to the "no raw SQL" rule — portable across sqlite/mysql/pgsql, verified by the 3-DB CI matrix.
            ->when($q !== '', fn ($query) => $query->whereRaw(
                'lower(name) like ?',
                ['%'.mb_strtolower($q).'%']
            ))
            // Order by the maintained name_lower column so the API tag order
            // matches the web UI (Web/BookmarkController) and is deterministic
            // across engines (Postgres orders `name` case-sensitively).
            ->orderBy('name_lower')
            ->get();

        return response()->json(
            TagResource::collection($tags)->resolve($request)
        );
    }
}
