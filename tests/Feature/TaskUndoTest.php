<?php

namespace Tests\Feature;

use App\Models\EditUndo;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskUndoTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_edit_can_be_undone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $task = $source->tasks()->create([
            'text' => 'Анхны үүрэг',
            'responsible' => 'А.Номин',
            'progress' => 0,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('tasks.update', $task), ['text' => 'Өөрчилсөн үүрэг'])
            ->assertRedirect();

        $this->assertSame('Өөрчилсөн үүрэг', $task->fresh()->text);
        $this->assertSame(1, EditUndo::query()->count());

        $this->actingAs($admin)->post(route('undo.store'))->assertRedirect();

        $this->assertSame('Анхны үүрэг', $task->fresh()->text);
        $this->assertSame(0, EditUndo::query()->count());
    }

    public function test_deleted_row_can_be_restored(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $task = $source->tasks()->create([
            'text' => 'Устгах үүрэг',
            'responsible' => 'Б.Болд',
            'collaborator' => 'АЗДТГ-ын дарга',
            'progress' => 10,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertSame(1, EditUndo::query()->count());

        $this->actingAs($admin)->post(route('undo.store'))->assertRedirect();

        $restored = Task::query()->where('text', 'Устгах үүрэг')->first();
        $this->assertNotNull($restored);
        $this->assertSame('Б.Болд', $restored->responsible);
        $this->assertSame('АЗДТГ-ын дарга', $restored->collaborator);
        $this->assertSame(10, $restored->progress);
    }

    public function test_index_exposes_undo_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $task = $source->tasks()->create([
            'text' => 'Тест',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('tasks.update', $task), ['progress' => 50])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('undoCount', 1));
    }
}
