<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RoleMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialist_only_sees_menus_from_role_template(): void
    {
        RolePermission::replaceFor('specialist', [
            'tasks' => 'manage',
            'leaves' => 'view',
        ]);

        $user = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => true,
            'is_department_head' => false,
        ]);

        // Хуучин/илүү эрх — ролийн загвараас гадуурх цэс харагдах ёсгүй.
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'phone_directory',
            'level' => 'manage',
        ]);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'decrees',
            'level' => 'view',
        ]);

        $this->assertTrue(ModuleAccess::canView($user, 'tasks'));
        $this->assertTrue(ModuleAccess::canView($user, 'leaves'));
        $this->assertFalse(ModuleAccess::canView($user, 'phone_directory'));
        $this->assertFalse(ModuleAccess::canView($user, 'decrees'));

        $keys = collect(ModuleAccess::navFor($user))
            ->flatMap(fn ($g) => collect($g['items'])->pluck('key'))
            ->all();

        $this->assertContains('dept_dashboard', $keys);
        $this->assertContains('tasks', $keys);
        $this->assertContains('leaves', $keys);
        $this->assertNotContains('phone_directory', $keys);
        $this->assertNotContains('decrees', $keys);
    }

    public function test_saving_role_template_syncs_specialist_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => true,
            'is_department_head' => false,
        ]);

        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'phone_directory',
            'level' => 'manage',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['tasks' => 'view'],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_module_permissions', [
            'user_id' => $user->id,
            'module_key' => 'phone_directory',
        ]);
        $this->assertDatabaseHas('user_module_permissions', [
            'user_id' => $user->id,
            'module_key' => 'tasks',
            'level' => 'view',
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('moduleNav', function ($nav) {
                    $keys = collect($nav)->flatMap(fn ($g) => collect($g['items'])->pluck('key'));

                    return $keys->contains('tasks')
                        && ! $keys->contains('phone_directory');
                })
            );
    }
}
