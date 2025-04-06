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
            $table->text('processing_error')->nullable()->after('status');
            $table->integer('processing_attempts')->default(0)->after('processing_error');
            $table->float('processing_duration')->nullable()->after('processing_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['processing_error', 'processing_attempts', 'processing_duration']);
        });
    }
};
