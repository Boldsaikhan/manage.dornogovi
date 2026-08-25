<?php

use App\Models\Task;
use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->string('key', 32)->nullable()->after('id');
        });

        // Хуучин үүрэг/өгөгдлийг бүрэн цэвэрлээд шинэ 2 төрөл үүсгэнэ.
        Task::query()->delete();
        TaskSource::query()->delete();

        TaskSource::create([
            'key' => 'directive',
            'name' => 'Үүрэг чиглэл',
            'period' => null,
            'sort_order' => 1,
        ]);

        TaskSource::create([
            'key' => 'prep_plan',
            'name' => 'Бэлтгэл ажил хангах төлөвлөгөө',
            'period' => null,
            'sort_order' => 2,
        ]);

        Schema::table('task_sources', function (Blueprint $table) {
            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
