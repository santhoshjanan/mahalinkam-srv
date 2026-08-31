<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('format');                      // html|csv|json|txt
            $t->string('status')->default('pending');  // pending|processing|completed|failed
            $t->integer('total_rows')->nullable();
            $t->integer('processed_rows')->default(0);
            $t->integer('created_count')->default(0);
            $t->integer('duplicate_count')->default(0);
            $t->integer('error_count')->default(0);
            $t->json('errors')->nullable();
            $t->string('original_filename');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
