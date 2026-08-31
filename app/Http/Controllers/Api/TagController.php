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
            ->when($q !== '', fn ($query) => $query->whereRaw(
                'lower(name) like ?',
                ['%'.mb_strtolower($q).'%']
            ))
            ->orderBy('name')
            ->get();

        return response()->json(
            TagResource::collection($tags)->resolve($request)
        );
    }
}
