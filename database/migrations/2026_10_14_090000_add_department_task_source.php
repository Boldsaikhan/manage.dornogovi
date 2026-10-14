<?php

use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;

/**
 * «Хэлтсийн үүрэг даалгавар» хэсэг нэмж, удирдлагын хэсгийн нэрийг
 * албан тушаалын бүтэн нэршлээр шинэчилнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        $order = -count(TaskSource::LEADERSHIP) - 1;

        foreach (TaskSource::LEADERSHIP as $key => $name) {
            $source = TaskSource::query()->firstOrNew(['key' => $key]);

            $source->name = $name;
            $source->layout = TaskSource::KEY_DIRECTIVE;
            $source->sort_order = $order;
            $source->save();

            $order++;
        }

        $department = TaskSource::query()->firstOrNew(['key' => TaskSource::KEY_DEPARTMENT]);

        $department->name = TaskSource::DEPARTMENT_NAME;
        $department->layout = TaskSource::KEY_DIRECTIVE;
        $department->sort_order = $order;
        $department->save();
    }

    public function down(): void
    {
        // Устгахгүй — байнгын хэсгүүд бөгөөд мөр агуулсан байж болно.
    }
};
