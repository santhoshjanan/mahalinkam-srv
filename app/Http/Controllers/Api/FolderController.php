<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FolderDepthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Resources\FolderResource;
use App\Services\FolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $folders = $request->user()->folders()
            ->orderBy('position')
            ->get();

        return response()->json(
            FolderResource::collection($folders)->resolve($request)
        );
    }

    public function store(StoreFolderRequest $request, FolderService $service): JsonResponse
    {
        try {
            $folder = $service->create(
                $request->user(),
                $request->validated('name'),
                $request->validated('parent_id'),
            );
        } catch (FolderDepthException $e) {
            // Match the API's domain-error convention (see BookmarkController::store):
            // a 422 keyed to the offending field, which the extension surfaces inline.
            throw ValidationException::withMessages(['parent_id' => $e->getMessage()]);
        }

        // Unwrapped, to match index() and the extension's `Folder` client type.
        return response()->json(
            (new FolderResource($folder))->resolve($request),
            201,
        );
    }
}
