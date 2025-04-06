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
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('adaptive_streaming')->default(false)->after('status');
            $table->boolean('is_protected')->default(false)->after('adaptive_streaming');
            $table->string('drm_type')->nullable()->after('is_protected');
            $table->boolean('has_subtitles')->default(false)->after('drm_type');
            $table->json('subtitle_languages')->nullable()->after('has_subtitles');
            $table->string('content_rating')->default('G')->after('subtitle_languages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'adaptive_streaming',
                'is_protected',
                'drm_type',
                'has_subtitles',
                'subtitle_languages',
                'content_rating',
            ]);
        });
    }
}; 