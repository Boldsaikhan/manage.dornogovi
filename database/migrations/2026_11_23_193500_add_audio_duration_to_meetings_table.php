<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedInteger('audio_duration_sec')->nullable()->after('audio_size');
            $table->unsignedInteger('transcription_position_sec')->default(0)->after('transcription_progress');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['audio_duration_sec', 'transcription_position_sec']);
        });
    }
};
