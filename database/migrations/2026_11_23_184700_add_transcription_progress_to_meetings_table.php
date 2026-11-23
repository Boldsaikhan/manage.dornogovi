<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unsignedTinyInteger('transcription_progress')->default(0)->after('transcription_status');
            $table->string('transcription_stage', 64)->nullable()->after('transcription_progress');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['transcription_progress', 'transcription_stage']);
        });
    }
};
