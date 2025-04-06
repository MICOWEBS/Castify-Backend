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
        // Add indexes to videos table
        Schema::table('videos', function (Blueprint $table) {
            $table->index('status');
            $table->index('user_id');
            $table->index('cloudinary_id');
            $table->index(['status', 'created_at']);
            $table->index('title');
        });

        // Add indexes to user_profiles table
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_default');
            $table->index(['user_id', 'is_default']);
        });

        // Add indexes to watch_history table
        Schema::table('watch_history', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('video_id');
            $table->index('watched_at');
            $table->index(['user_id', 'watched_at']);
        });

        // Add indexes to comments table
        Schema::table('comments', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('video_id');
            $table->index('created_at');
        });

        // Add indexes to category_video table
        Schema::table('category_video', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from videos table
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['cloudinary_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['title']);
        });

        // Remove indexes from user_profiles table
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_default']);
            $table->dropIndex(['user_id', 'is_default']);
        });

        // Remove indexes from watch_history table
        Schema::table('watch_history', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['video_id']);
            $table->dropIndex(['watched_at']);
            $table->dropIndex(['user_id', 'watched_at']);
        });

        // Remove indexes from comments table
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['video_id']);
            $table->dropIndex(['created_at']);
        });

        // Remove indexes from category_video table
        Schema::table('category_video', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['video_id']);
        });
    }
}; 