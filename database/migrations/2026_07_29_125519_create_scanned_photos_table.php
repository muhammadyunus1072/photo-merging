<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanned_photos', function (Blueprint $table) {
            $table->id();
            $table->string('folder')->index(); // 'a', 'b', 'c', etc.
            $table->string('filename');
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->timestamps();

            // Prevent duplicate entries if you scan the same folder multiple times
            $table->unique(['folder', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanned_photos');
    }
};
