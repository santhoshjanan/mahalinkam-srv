<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmark_tag', function (Blueprint $t) {
            $t->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['bookmark_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmark_tag');
    }
};
