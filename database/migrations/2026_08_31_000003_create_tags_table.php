<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('name_lower', 80);
            $t->timestamps();
            $t->unique(['user_id', 'name_lower']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
