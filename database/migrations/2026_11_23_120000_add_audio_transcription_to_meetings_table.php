<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('transcript');
            $table->string('audio_original_name')->nullable()->after('audio_path');
            $table->string('audio_mime', 120)->nullable()->after('audio_original_name');
            $table->unsignedBigInteger('audio_size')->nullable()->after('audio_mime');
            $table->string('transcription_status', 24)->default('none')->after('audio_size');
            $table->text('transcription_error')->nullable()->after('transcription_status');
            $table->timestamp('transcribed_at')->nullable()->after('transcription_error');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'audio_path',
                'audio_original_name',
                'audio_mime',
                'audio_size',
                'transcription_status',
                'transcription_error',
                'transcribed_at',
            ]);
        });
    }
};
