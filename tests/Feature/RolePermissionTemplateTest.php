<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
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
}
