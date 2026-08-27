<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskEditLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_level_can_update_row_but_not_create_source(): void
    {
        RolePermission::replaceFor('specialist', [
            'tasks' => 'edit',
        ]);

        $user = User::factory()->create([
            'name' => 'Доржбатын Саран',
            'is_admin' => false,
            'is_specialist' => true,
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->firstOrFail();
        $task = Task::create([
            'task_source_id' => $source->id,
            'text' => 'Хуучин текст',
            'responsible' => 'П.Гантуяа',
            'progress' => 10,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('canEdit', true)
                ->where('canManage', false)
            );

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['text' => 'Шинэчилсэн текст', 'progress' => 40])
            ->assertRedirect();

        $this->assertSame('Шинэчилсэн текст', $task->fresh()->text);
        $this->assertSame(40, (int) $task->fresh()->progress);

        $this->actingAs($user)
            ->post(route('tasks.sources.store'), [
                'name' => 'Шинэ хэсэг',
                'copy_from' => TaskSource::KEY_DIRECTIVE,
            ])
            ->assertForbidden();
    }
}
