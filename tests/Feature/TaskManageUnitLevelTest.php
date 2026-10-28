<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Удирдах (хэлтсийн)» — зөвхөн өөрийн нэгжийн үүрэг даалгаврыг удирдана.
 */
class TaskManageUnitLevelTest extends TestCase
{
    use RefreshDatabase;

    private function unitManager(string $departmentName = 'Хэвлэл мэдээллийн алба'): User
    {
        $department = Department::create([
            'name' => $departmentName,
            'code' => substr(md5($departmentName), 0, 6),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['department_id' => $department->id]);

        $this->grantModule($user, 'tasks', 'manage_unit');

        return $user->fresh();
    }

    public function test_the_level_is_offered_for_tasks_only(): void
    {
        $this->assertTrue(ModuleAccess::supportsUnitScope('tasks'));
        $this->assertTrue(ModuleAccess::supportsUnitScope('tasks:edit'));
        $this->assertFalse(ModuleAccess::supportsUnitScope('leaves'));
        $this->assertSame('Удирдах (хэлтсийн)', ModuleAccess::levelLabel('manage_unit'));
    }

    public function test_it_grants_edit_and_manage_rights(): void
    {
        $user = $this->unitManager();

        $this->assertTrue(ModuleAccess::canView($user, 'tasks'));
        $this->assertTrue(ModuleAccess::canEdit($user, 'tasks'));
        $this->assertTrue(ModuleAccess::canManage($user, 'tasks'));
        $this->assertTrue(ModuleAccess::scopeUnitOnly($user, 'tasks'));
    }

    public function test_only_the_own_unit_section_is_listed(): void
    {
        $user = $this->unitManager();
        $keys = [];

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('tasks.index'))
            ->assertInertia(function (AssertableInertia $page) use (&$keys) {
                $keys = collect($page->toArray()['props']['kinds'])->pluck('key')->all();
            });

        $this->assertSame(['unit_02'], $keys);
    }

    public function test_tasks_of_the_own_unit_can_be_edited(): void
    {
        $user = $this->unitManager();
        $source = TaskSource::where('key', 'unit_02')->firstOrFail();

        $task = Task::create([
            'task_source_id' => $source->id,
            'text' => 'Нэгжийн үүрэг',
            'responsible' => 'Өөр хүн',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['note' => 'Хийгдэж байна'])
            ->assertRedirect();

        $this->assertSame('Хийгдэж байна', $task->fresh()->note);
    }

    public function test_tasks_of_another_section_cannot_be_edited(): void
    {
        $user = $this->unitManager();
        $other = TaskSource::where('key', 'unit_03')->firstOrFail();

        $task = Task::create([
            'task_source_id' => $other->id,
            'text' => 'Өөр нэгжийн үүрэг',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['note' => 'Оролдлого'])
            ->assertForbidden();

        $this->assertNull($task->fresh()->note);
    }

    public function test_a_row_cannot_be_added_to_another_section(): void
    {
        $user = $this->unitManager();

        $this->actingAs($user)
            ->post(route('tasks.store'), ['kind' => 'unit_03', 'text' => 'Оролдлого'])
            ->assertForbidden();

        $this->actingAs($user)
            ->from(route('tasks.index', ['kind' => 'unit_02']))
            ->post(route('tasks.store'), ['kind' => 'unit_02', 'text' => 'Миний нэгжийн үүрэг'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['text' => 'Миний нэгжийн үүрэг']);
    }
}
