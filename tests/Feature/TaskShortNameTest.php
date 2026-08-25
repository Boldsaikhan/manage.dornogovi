<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskShortNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_are_shortened_on_save_and_display(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('tasks.store'), [
            'kind' => 'directive',
            'text' => 'Тест',
            'responsible' => 'Цагаанмаам Мөнхбат/Ц.Мөнх-Эрдэнэ',
            'collaborator' => 'АЗДТГ-ын дарга',
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->assertSame('Ц.Мөнхбат/Ц.Мөнх-Эрдэнэ', $task->responsible);
        // Албан тушаал хэвээр үлдэнэ
        $this->assertSame('АЗДТГ-ын дарга', $task->collaborator);

        // Хуучин бүтэн нэртэй мөр ч жагсаалтад богиноор гарна
        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $source->tasks()->create(['text' => 'Хуучин', 'responsible' => 'Мөнхбаатар Мөнхбат', 'sort_order' => 9]);

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tasks.1.responsible', 'М.Мөнхбат'));

        $this->actingAs($admin)->patch(route('tasks.update', $task), [
            'responsible' => 'Батбаярын Дулмаа',
        ])->assertRedirect();

        $this->assertSame('Б.Дулмаа', $task->fresh()->responsible);
    }
}
