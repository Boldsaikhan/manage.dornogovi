<?php

namespace Tests\Feature;

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
                ->where('rolePermissions.specialist.tasks', 'manage'));
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
}
