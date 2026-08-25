<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskSource;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        Task::query()->delete();

        TaskSource::updateOrCreate(
            ['key' => TaskSource::KEY_DIRECTIVE],
            ['name' => 'Үүрэг чиглэл', 'period' => null, 'sort_order' => 1],
        );

        TaskSource::updateOrCreate(
            ['key' => TaskSource::KEY_PREP_PLAN],
            ['name' => 'Бэлтгэл ажил хангах төлөвлөгөө', 'period' => null, 'sort_order' => 2],
        );
    }
}
