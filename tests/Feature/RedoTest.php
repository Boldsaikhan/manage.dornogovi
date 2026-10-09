<?php

namespace Tests\Feature;

use App\Models\EditUndo;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Буцаасан үйлдлийг дахин хийх.
 */
class RedoTest extends TestCase
{
    use RefreshDatabase;

    private function task(): Task
    {
        $source = TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        return $source->tasks()->create(['text' => 'Анхны текст', 'sort_order' => 1]);
    }

    public function test_an_edit_can_be_undone_and_redone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['text' => 'Шинэчилсэн текст']);

        $this->assertSame('Шинэчилсэн текст', $task->fresh()->text);

        // Буцаах.
        $this->actingAs($admin)->from(route('tasks.index'))->post(route('undo.store'));
        $this->assertSame('Анхны текст', $task->fresh()->text);

        // Дахин хийх.
        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('redo.store'))
            ->assertRedirect();

        $this->assertSame('Шинэчилсэн текст', $task->fresh()->text);
    }

    public function test_a_delete_can_be_undone_and_redone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->delete(route('tasks.destroy', $task));

        $this->assertSame(0, Task::query()->count());

        $this->actingAs($admin)->from(route('tasks.index'))->post(route('undo.store'));
        $this->assertSame(1, Task::query()->count());

        // Дахин устгана.
        $this->actingAs($admin)->from(route('tasks.index'))->post(route('redo.store'));
        $this->assertSame(0, Task::query()->count());
    }

    public function test_a_new_edit_clears_the_redo_stack(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['text' => 'Хоёр дахь']);

        $this->actingAs($admin)->from(route('tasks.index'))->post(route('undo.store'));

        $this->assertSame(1, EditUndo::query()->where('kind', EditUndo::REDO)->count());

        // Шинэ үйлдэл хийвэл дахин хийх нь утгаа алдана.
        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['text' => 'Гурав дахь']);

        $this->assertSame(0, EditUndo::query()->where('kind', EditUndo::REDO)->count());
    }

    public function test_nothing_to_redo_is_reported(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('redo.store'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Дахин хийх үйлдэл алга.');
    }

    public function test_the_page_shows_both_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['text' => 'Шинэ']);

        $this->actingAs($admin)->from(route('tasks.index'))->post(route('undo.store'));

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('undoCount', 0)
                ->where('redoCount', 1));
    }
}
