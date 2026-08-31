<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_access_page_shares_role_templates(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('roles', 3)
                ->where('rolePermissions.specialist.tasks', 'manage_own'));
    }

    public function test_admin_can_save_role_template(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['tasks' => 'view', 'leaves' => 'manage', 'unknown_module' => 'manage'],
            ])
            ->assertRedirect();

        $this->assertSame(
            ['tasks' => 'view', 'leaves' => 'manage'],
            RolePermission::map()['specialist'],
        );
    }

    public function test_role_template_save_ignores_empty_permission_values(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => [
                    'tasks' => 'edit_own',
                    'work_groups' => '',
                    '__none__' => 'view',
                ],
                'label' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('edit_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'tasks')
            ->value('level'));
        $this->assertDatabaseMissing('role_permissions', [
            'role' => 'specialist',
            'module_key' => 'work_groups',
        ]);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.roles.update', 'guest'), ['permissions' => []])
            ->assertNotFound();
    }

    public function test_admin_can_add_and_remove_a_custom_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['label' => 'Архивч', 'copy_from' => 'specialist'])
            ->assertRedirect();

        $role = Role::query()->where('label', 'Архивч')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertSame(RolePermission::map()['specialist'], RolePermission::map()[$role->key]);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role->key))
            ->assertRedirect();

        $this->assertDatabaseMissing('roles', ['key' => $role->key]);
        $this->assertDatabaseMissing('role_permissions', ['role' => $role->key]);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())
            ->delete(route('admin.roles.destroy', 'specialist'))
            ->assertForbidden();
    }

    public function test_specialist_template_keeps_view_own_after_save(): void
    {
        $admin = $this->admin();
        $defaults = RolePermission::DEFAULTS['specialist'];

        foreach (range(1, 8) as $i) {
            $user = User::factory()->create([
                'is_admin' => false,
                'is_specialist' => true,
                'is_department_head' => false,
            ]);

            foreach ($defaults as $module => $level) {
                UserModulePermission::create([
                    'user_id' => $user->id,
                    'module_key' => $module,
                    'level' => $level,
                ]);
            }
        }

        $permissions = [...$defaults, 'tasks' => 'view_own'];

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => $permissions,
            ])
            ->assertRedirect(route('admin.users.index', [
                'tab' => 'templates',
                'role' => 'specialist',
            ]))
            ->assertSessionHas('success');

        $this->assertSame('view_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'tasks')
            ->value('level'));
        $this->assertSame('view_own', RolePermission::map()['specialist']['tasks']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rolePermissions.specialist.tasks', 'view_own'));

        $this->assertSame(8, UserModulePermission::query()
            ->where('module_key', 'tasks')
            ->where('level', 'view_own')
            ->count());
    }

    public function test_regulations_own_scope_saves_and_syncs_to_specialists(): void
    {
        $admin = $this->admin();
        $specialist = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => true,
            'is_department_head' => false,
        ]);

        UserModulePermission::create([
            'user_id' => $specialist->id,
            'module_key' => 'regulations',
            'level' => 'view',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['regulations' => 'view_own', 'tasks' => 'manage_own'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('view_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'regulations')
            ->value('level'));
        $this->assertSame('view_own', UserModulePermission::query()
            ->where('user_id', $specialist->id)
            ->where('module_key', 'regulations')
            ->value('level'));

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rolePermissions.specialist.regulations', 'view_own')
                ->where('users', fn ($users) => collect($users)->contains(
                    fn ($u) => (int) $u['id'] === $specialist->id
                        && ($u['permissions']['regulations'] ?? null) === 'view_own'
                )));
    }
}
