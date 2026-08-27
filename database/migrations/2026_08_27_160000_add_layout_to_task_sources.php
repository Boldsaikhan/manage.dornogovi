<?php

use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->string('layout', 32)->nullable()->after('key');
        });

        TaskSource::query()->each(function (TaskSource $source): void {
            $layout = in_array($source->key, TaskSource::LAYOUTS, true)
                ? $source->key
                : TaskSource::KEY_DIRECTIVE;

            $source->update(['layout' => $layout]);
        });
    }

    public function down(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
