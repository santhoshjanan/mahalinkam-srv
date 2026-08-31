<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $t->text('url');
            // 768 chars keeps the (user_id, normalized_url) unique index within
            // MySQL's utf8mb4 3072-byte index limit (768 * 4 = 3072).
            $t->string('normalized_url', 768);
            $t->string('title', 1024)->nullable();
            $t->text('description')->nullable();
            $t->string('favicon_url', 2048)->nullable();
            $t->string('metadata_status')->default('pending');
            $t->timestamps();
            $t->unique(['user_id', 'normalized_url']);
            $t->index(['user_id', 'folder_id']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
