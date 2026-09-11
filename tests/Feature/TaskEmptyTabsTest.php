<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Харах эрхтэй үүрэг даалгавар байхгүй үеийн байдал.
 */
class TaskEmptyTabsTest extends TestCase
{
    use RefreshDatabase;

    /** Зөвхөн өөрт нь хамаатай үүрэг хардаг хэрэглэгч. */
    private function ownScopeUser(): User
    {
        $role = Role::create(['key' => 'own_only', 'label' => 'Зөвхөн өөрийн']);
        RolePermission::create(['role' => $role->key, 'module_key' => 'tasks', 'level' => 'view_own']);

        return User::factory()->create(['is_admin' => false, 'role_key' => $role->key]);
    }

    public function test_no_tabs_are_invented_when_there_is_nothing_to_show(): void
    {
        TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        $user = $this->ownScopeUser();

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Хуурамч таб зохиохгүй.
                ->where('kinds', [])
                ->where('kind', ''));
    }

    public function test_a_hidden_tab_cannot_be_downloaded(): void
    {
        TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        $user = $this->ownScopeUser();

        $this->actingAs($user)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'xlsx']))
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('tasks.logs', ['kind' => 'directive']))
            ->assertForbidden();
    }

    public function test_an_admin_still_sees_the_real_tabs(): void
    {
        TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kinds.0.key', 'directive')
                ->where('kinds.0.label', 'Үүрэг чиглэл'));
    }
}
