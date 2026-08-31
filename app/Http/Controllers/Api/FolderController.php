<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FolderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
