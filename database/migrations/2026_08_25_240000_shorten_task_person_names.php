<?php

use App\Models\Task;
use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Хариуцах эзэн, хяналт тавих албан тушаалтны нэрийг «Ц.Мөнхбат» хэлбэрт оруулна.
        Task::query()
            ->select(['id', 'responsible', 'collaborator'])
            ->chunkById(200, function ($tasks) {
                foreach ($tasks as $task) {
                    $responsible = PersonName::shortList($task->responsible) ?: null;
                    $collaborator = PersonName::shortList($task->collaborator) ?: null;

                    if ($responsible === $task->responsible && $collaborator === $task->collaborator) {
                        continue;
                    }

                    $task->forceFill([
                        'responsible' => $responsible,
                        'collaborator' => $collaborator,
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Бүтэн овгийг сэргээх боломжгүй.
    }
};
