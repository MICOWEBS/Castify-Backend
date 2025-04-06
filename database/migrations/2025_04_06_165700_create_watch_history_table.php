<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_profile_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('watched_seconds')->default(0);
            $table->integer('video_duration')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('last_watched_at');
            $table->timestamps();
            
            // Each profile can have only one watch history entry per video
            $table->unique(['user_profile_id', 'video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_history');
    }
}; 