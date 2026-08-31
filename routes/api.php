<?php

use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/ping', PingController::class)->name('api.ping');
    Route::get('/folders', [FolderController::class, 'index'])->name('api.folders.index');
    Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');

    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('api.bookmarks.index');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('api.bookmarks.store');
    // Registered before the {bookmark} routes, and the id routes are constrained to
    // digits, so `/api/bookmarks/lookup` never binds as {bookmark} = "lookup".
    Route::get('/bookmarks/lookup', [BookmarkController::class, 'lookup'])->name('api.bookmarks.lookup');
    Route::patch('/bookmarks/{bookmark}', [BookmarkController::class, 'update'])
        ->whereNumber('bookmark')->name('api.bookmarks.update');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])
        ->whereNumber('bookmark')->name('api.bookmarks.destroy');
});
