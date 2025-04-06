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
            $table->string('cloudinary_id')->nullable()->after('thumbnail');
            $table->string('cloudinary_version')->nullable()->after('cloudinary_id');
            $table->string('streaming_url')->nullable()->after('cloudinary_version');
            $table->string('format')->nullable()->after('streaming_url');
            $table->float('duration')->nullable()->after('format');
            $table->bigInteger('file_size')->nullable()->after('duration');
            $table->integer('width')->nullable()->after('file_size');
            $table->integer('height')->nullable()->after('width');
            $table->string('file_name')->nullable()->after('height');
            $table->text('error_message')->nullable()->after('file_name');
            $table->timestamp('processed_at')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'cloudinary_id',
                'cloudinary_version',
                'streaming_url',
                'format',
                'duration',
                'file_size',
                'width',
                'height',
                'file_name',
                'error_message',
                'processed_at',
            ]);
        });
    }
}; 