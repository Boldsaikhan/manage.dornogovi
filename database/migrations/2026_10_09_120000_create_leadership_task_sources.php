<?php

use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;

/**
 * Удирдлагын байнгын үүрэг даалгаврын хэсгүүдийг үүсгэнэ.
 *
 * «Аймгийн Засаг дарга», «Засаг даргын орлогч», «Тамгын газрын дарга»
 * гурав нь жагсаалтын эхэнд байрлаж, устгагдахгүй.
 */
return new class extends Migration
{
    public function up(): void
    {
        $order = -count(TaskSource::LEADERSHIP);

        foreach (TaskSource::LEADERSHIP as $key => $name) {
            $source = TaskSource::query()->firstOrNew(['key' => $key]);

            $source->name = $name;
            $source->layout = TaskSource::KEY_DIRECTIVE;
            $source->sort_order = $order;
            $source->save();

            $order++;
        }
    }

    public function down(): void
    {
        // Устгахгүй — байнгын хэсгүүд бөгөөд мөр агуулсан байж болно.
    }
};
