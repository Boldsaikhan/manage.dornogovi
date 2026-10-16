<?php

use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;

/**
 * Байнгын хэсгүүдийн эрэмбийг сөрөг биш утгаар дахин тавина.
 *
 * `task_sources.sort_order` нь unsigned тул сөрөг утга MySQL дээр
 * «Out of range» алдаа өгч, migration зогсож байв.
 */
return new class extends Migration
{
    public function up(): void
    {
        $locked = [...array_keys(TaskSource::LEADERSHIP), TaskSource::KEY_DEPARTMENT];

        // Бусад хэсгүүд байнгынхаас хойш.
        TaskSource::query()
            ->whereNotIn('key', $locked)
            ->where('sort_order', '<', 10)
            ->update(['sort_order' => 10]);

        $order = 0;

        foreach ($locked as $key) {
            TaskSource::query()
                ->where('key', $key)
                ->update(['sort_order' => $order]);

            $order++;
        }
    }

    public function down(): void
    {
        // Эрэмбэ — буцаах шаардлагагүй.
    }
};
