<?php

use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/ping', PingController::class)->name('api.ping');
    Route::get('/folders', [FolderController::class, 'index'])->name('api.folders.index');
    Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
});
