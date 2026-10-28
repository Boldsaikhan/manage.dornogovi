<?php

use App\Models\Department;
use App\Models\TaskSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нэгж тус бүрийн дотоодын үүрэг даалгавар.
 *
 * «Хэлтсийн үүрэг даалгавар» хэсэгт нэгж бүр өөрийн хуудастай болж,
 * тухайн нэгжийн албан хаагчдад л харагдана.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('key')
                ->constrained('departments')->nullOnDelete();
            $table->string('unit_name')->nullable()->after('department_id');
        });

        // Байгаа хэлтсүүдийг нэрээр нь тааруулна («7. Цэргийн штаб» ч тохирно).
        $departments = Department::query()->get(['id', 'name'])
            ->keyBy(fn (Department $d) => TaskSource::normalizeUnitName($d->name));

        $order = (int) TaskSource::query()->max('sort_order');

        foreach (TaskSource::UNITS as $key => $name) {
            $order++;

            $source = TaskSource::query()->firstOrNew(['key' => $key]);

            $source->name = $name;
            $source->unit_name = $name;
            $source->department_id = $departments
                ->get(TaskSource::normalizeUnitName($name))?->id;
            $source->layout = TaskSource::KEY_DIRECTIVE;

            if (! $source->exists) {
                $source->sort_order = $order;
            }

            $source->save();
        }
    }

    public function down(): void
    {
        TaskSource::query()->whereIn('key', array_keys(TaskSource::UNITS))
            ->whereDoesntHave('tasks')
            ->delete();

        Schema::table('task_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('unit_name');
        });
    }
};
