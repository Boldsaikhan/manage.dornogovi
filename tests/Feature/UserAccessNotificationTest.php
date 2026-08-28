<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_without_changes_shows_info_flash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Б.Тест',
            'email' => 'test@example.com',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Б.Тест',
            'email' => 'test@example.com',
            'phone' => $user->phone,
            'department_id' => null,
            'position' => null,
            'is_admin' => false,
            'is_department_head' => false,
            'is_specialist' => false,
            'permissions' => [],
        ])->assertRedirect()
            ->assertSessionHas('info', 'Өөрчлөлт оруулаагүй байна.');
    }

    public function test_permission_change_shows_warning_flash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Б.Тест',
            'email' => 'perm@example.com',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Б.Тест',
            'email' => 'perm@example.com',
            'phone' => null,
            'department_id' => null,
            'position' => null,
            'is_admin' => false,
            'is_department_head' => false,
            'is_specialist' => false,
            'permissions' => ['decrees' => 'manage'],
        ])->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame('manage', UserModulePermission::query()->where('user_id', $user->id)->value('level'));
    }

    public function test_user_update_ignores_empty_permission_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Б.Тест',
            'email' => 'emptyperm@example.com',
            'is_specialist' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Б.Тест',
            'email' => 'emptyperm@example.com',
            'phone' => '',
            'department_id' => '',
            'position' => '',
            'is_admin' => false,
            'is_department_head' => false,
            'is_specialist' => true,
            'permissions' => [
                'tasks' => 'edit_own',
                'leaves' => '',
                '__none__' => 'view',
            ],
        ])->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame('edit_own', UserModulePermission::query()
            ->where('user_id', $user->id)
            ->where('module_key', 'tasks')
            ->value('level'));
        $this->assertDatabaseMissing('user_module_permissions', [
            'user_id' => $user->id,
            'module_key' => 'leaves',
        ]);
    }

    public function test_profile_change_shows_success_flash(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'name' => 'Хуучин',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Шинэ нэр',
            'email' => 'old@example.com',
            'phone' => null,
            'department_id' => null,
            'position' => null,
            'is_admin' => false,
            'is_department_head' => false,
            'is_specialist' => false,
            'permissions' => [],
        ])->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Шинэ нэр', $user->name);
    }
}
