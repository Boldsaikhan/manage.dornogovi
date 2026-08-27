<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleOwnScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_own_sees_only_related_leaves(): void
    {
        $viewer = User::factory()->create(['name' => 'Б. Болдсайхан']);
        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'leaves',
            'level' => 'view_own',
        ]);

        Leave::create([
            'user_id' => $viewer->id,
            'person_name' => 'Б. Болдсайхан',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'approved',
        ]);

        Leave::create([
            'user_id' => User::factory()->create()->id,
            'person_name' => 'Д. Баттуяа',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'approved',
        ]);

        $this->actingAs($viewer)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.person_name', 'Б. Болдсайхан'));
    }

    public function test_admin_can_save_own_scope_permission_on_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => ['tasks' => 'view_own', 'regulations' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('view_own', UserModulePermission::query()
            ->where('user_id', $user->id)
            ->where('module_key', 'tasks')
            ->value('level'));

        $this->assertDatabaseMissing('user_module_permissions', [
            'user_id' => $user->id,
            'module_key' => 'regulations',
        ]);
    }

    public function test_role_template_accepts_own_scope_levels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['tasks' => 'manage_own', 'leaves' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('manage_own', \App\Models\RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'tasks')
            ->value('level'));
    }
}
