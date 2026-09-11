<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Үүрэг даалгаварт татах, засах, оруулах эрхийг тусад нь тохируулах.
 */
class TaskSubPermissionTest extends TestCase
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
    private function userWithLevels(array $levels): User
    {
        $role = Role::create(['key' => 'test_role', 'label' => 'Туршилт']);

        foreach ($levels as $module => $level) {
            RolePermission::create(['role' => $role->key, 'module_key' => $module, 'level' => $level]);
        }

        return User::factory()->create(['is_admin' => false, 'role_key' => $role->key]);
    }

    public function test_the_three_actions_appear_as_separate_permissions(): void
    {
        $subs = ModuleAccess::SUB_MODULES['tasks'];

        $this->assertSame(
            ['edit', 'export', 'import'],
            array_keys($subs),
        );
    }

    public function test_an_unset_sub_permission_follows_the_parent(): void
    {
        $this->source();

        $user = $this->userWithLevels(['tasks' => 'edit']);

        // Дэд эрх тохируулаагүй тул эцгийнхээ түвшнийг өвлөнө.
        $this->assertTrue(ModuleAccess::canEdit($user, 'tasks:edit'));
        $this->assertTrue(ModuleAccess::canView($user, 'tasks:export'));
    }

    public function test_downloading_can_be_closed_on_its_own(): void
    {
        $this->source();

        $user = $this->userWithLevels(['tasks' => 'edit', 'tasks:export' => 'closed']);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canExport', false)
                // Мөр засах эрх нь хэвээр.
                ->where('canEdit', true));

        $this->actingAs($user)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'xlsx']))
            ->assertForbidden();
    }

    public function test_editing_can_be_closed_while_downloading_stays_open(): void
    {
        $source = $this->source();

        $user = $this->userWithLevels(['tasks' => 'edit', 'tasks:edit' => 'view']);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canEdit', false)
                ->where('canExport', true));

        $this->actingAs($user)
            ->post(route('tasks.store'), ['kind' => 'directive'])
            ->assertForbidden();

        $this->assertSame(0, $source->tasks()->count());
    }

    public function test_importing_needs_both_manage_and_the_sub_permission(): void
    {
        $this->source();

        // Удирдах эрхтэй ч оруулахыг нь хаасан.
        $user = $this->userWithLevels(['tasks' => 'manage', 'tasks:import' => 'closed']);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canImport', false));

        $this->actingAs($user)
            ->post(route('tasks.documents.preview'), ['kind' => 'directive'])
            ->assertForbidden();
    }
}
