<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\BookmarkController;
use App\Http\Controllers\Web\BulkBookmarkController;
use App\Http\Controllers\Web\FolderController;
use App\Http\Controllers\Web\RefetchMetadataController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [BookmarkController::class, 'index'])->name('bookmarks.index');

    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::post('/bookmarks/bulk', BulkBookmarkController::class)->name('bookmarks.bulk');
    Route::patch('/bookmarks/{bookmark}', [BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::post('/bookmarks/{bookmark}/refetch', RefetchMetadataController::class)->name('bookmarks.refetch');

    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::patch('/folders/{folder}/move', [FolderController::class, 'move'])->name('folders.move');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
});

// Keep the Breeze-named "dashboard" route resolvable; the app home is "/".
Route::get('/dashboard', fn () => redirect()->route('bookmarks.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
