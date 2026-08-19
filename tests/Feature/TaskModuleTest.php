<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    private function task(array $attributes = []): Task
    {
        $source = TaskSource::create(['name' => '2026.07.09 — Үүрэг, чиглэл', 'period' => '07.09', 'sort_order' => 1]);

        return Task::create(array_merge([
            'task_source_id' => $source->id,
            'text' => 'Албан хаагчдад сургалт зохион байгуулах',
            'period' => '07.09',
            'responsible' => 'Ц.Сансармаа',
            'progress' => 0,
            'sort_order' => 1,
        ], $attributes));
    }

    public function test_guests_cannot_see_the_module(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_page_lists_tasks_and_sources(): void
    {
        $task = $this->task();

        $this->actingAs(User::factory()->create())
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->has('sources', 1)
                ->has('tasks', 1)
                ->where('tasks.0.text', $task->text)
            );
    }

    public function test_progress_is_saved_and_clamped_by_validation(): void
    {
        $task = $this->task();

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['progress' => 60])
            ->assertRedirect();

        $this->assertSame(60, $task->fresh()->progress);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['progress' => 140])
            ->assertSessionHasErrors('progress');
    }

    public function test_department_can_be_assigned_to_every_task_of_one_responsible(): void
    {
        $first = $this->task();
        $second = Task::create([
            'task_source_id' => $first->task_source_id,
            'text' => 'Хүний нөөцийн судалгаа хийх',
            'responsible' => 'Ц.Сансармаа',
            'progress' => 0,
            'sort_order' => 2,
        ]);
        $other = Task::create([
            'task_source_id' => $first->task_source_id,
            'text' => 'Өөр хүний ажил',
            'responsible' => 'Н.Мөнхцэцэг',
            'progress' => 0,
            'sort_order' => 3,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.assign-department'), [
                'responsible' => 'Ц.Сансармаа',
                'department' => 'Төрийн захиргааны удирдлагын хэлтэс',
            ])
            ->assertRedirect();

        $this->assertSame('Төрийн захиргааны удирдлагын хэлтэс', $first->fresh()->department);
        $this->assertSame('Төрийн захиргааны удирдлагын хэлтэс', $second->fresh()->department);
        $this->assertNull($other->fresh()->department);
    }
}
