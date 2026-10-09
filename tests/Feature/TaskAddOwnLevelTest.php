<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Нэмэх, оруулах (хамааралтай)» эрх.
 *
 * Зөвхөн өөрт хамааралтай үүргийг хардаг ч шинэ мөр нэмж чадна.
 */
class TaskAddOwnLevelTest extends TestCase
{
    use RefreshDatabase;

    private function source(): TaskSource
    {
        return TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );
    }

    /** @param  array<string, string>  $levels */
    private function staff(array $levels, string $name = 'Б.Болд'): User
    {
        $role = Role::create(['key' => 'test_role', 'label' => 'Туршилт']);

        foreach ($levels as $module => $level) {
            RolePermission::create(['role' => $role->key, 'module_key' => $module, 'level' => $level]);
        }

        return User::factory()->create([
            'is_admin' => false,
            'name' => $name,
            'role_key' => $role->key,
        ])->fresh();
    }

    public function test_the_level_is_offered_for_tasks(): void
    {
        $this->assertContains('add_own', ModuleAccess::LEVELS);
        $this->assertSame('Нэмэх, оруулах (хамааралтай)', ModuleAccess::levelLabel('add_own'));
        $this->assertTrue(ModuleAccess::isOwnLevel('add_own'));
    }

    public function test_the_user_can_add_a_task_and_sees_only_their_own(): void
    {
        $source = $this->source();

        $user = $this->staff(['tasks' => 'add_own', 'tasks:edit' => 'add_own']);

        // Өөр хүний үүрэг — харагдахгүй.
        $source->tasks()->create(['text' => 'Өөр хүний үүрэг', 'responsible' => 'Ц.Сансармаа', 'sort_order' => 1]);

        $this->actingAs($user)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive', 'text' => 'Миний үүрэг'])
            ->assertRedirect();

        $added = Task::query()->where('text', 'Миний үүрэг')->firstOrFail();

        // Хариуцагч нь өөрөө болж тавигдана — эс бөгөөс харагдахгүй.
        $this->assertSame('Б.Болд', $added->responsible);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.text', 'Миний үүрэг')
                ->where('canEdit', true));
    }

    public function test_edit_own_still_cannot_add_rows(): void
    {
        $source = $this->source();

        // «Оруулах (хамааралтай)» нь хэрэгжилт бичих эрх — мөр нэмэхгүй.
        $user = $this->staff(['tasks' => 'edit_own', 'tasks:edit' => 'edit_own']);

        $this->actingAs($user)
            ->post(route('tasks.store'), ['kind' => 'directive', 'text' => 'Оролдлого'])
            ->assertForbidden();

        $this->assertSame(0, $source->tasks()->count());
    }

    public function test_the_level_does_not_grant_section_management(): void
    {
        $this->source();

        $user = $this->staff(['tasks' => 'add_own', 'tasks:edit' => 'add_own']);

        $this->assertFalse(ModuleAccess::canManage($user, 'tasks'));

        $this->actingAs($user)
            ->post(route('tasks.sources.store'), ['name' => 'Шинэ хэсэг'])
            ->assertForbidden();
    }
}
