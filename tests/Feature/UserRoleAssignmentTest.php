<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Албан хаагчид роль оноох — эрхгүй роль ч тэмдэглэгдэнэ.
 */
class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $label, array $permissions = []): Role
    {
        $role = Role::create([
            'key' => Role::keyFor($label),
            'label' => $label,
            'is_system' => false,
            'sort_order' => 90,
        ]);

        if ($permissions !== []) {
            RolePermission::replaceFor($role->key, $permissions);
        }

        return $role;
    }

    private function saveUser(User $admin, User $staff, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->patch(route('admin.users.update', $staff), array_merge([
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
        ], $payload));
    }

    public function test_an_empty_role_is_still_recorded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['name' => 'Б.Зоригтбаатар']);
        $role = $this->role('Хүний нөөц');

        // Эрх огт байхгүй роль — өмнө нь «Рольгүй» гэж үлддэг байсан.
        $this->saveUser($admin, $staff, ['role_key' => $role->key, 'permissions' => []])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($role->key, $staff->fresh()->role_key);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(function (AssertableInertia $page) use ($role) {
                $users = collect($page->toArray()['props']['users']);
                $row = $users->firstWhere('name', 'Б.Зоригтбаатар');

                $this->assertSame($role->key, $row['role_key']);
            });
    }

    public function test_the_assigned_role_grants_its_permissions_at_runtime(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create();
        $role = $this->role('Архивч', ['archives' => 'manage', 'decrees:tushaal_a' => 'view']);

        $this->saveUser($admin, $staff, ['role_key' => $role->key, 'permissions' => []])
            ->assertRedirect();

        $staff = $staff->fresh();

        $this->assertTrue(ModuleAccess::canManage($staff, 'archives'));
        $this->assertTrue(ModuleAccess::canView($staff, 'decrees:tushaal_a'));
        $this->assertFalse(ModuleAccess::canView($staff, 'contracts'));
    }

    public function test_changing_the_role_template_reaches_assigned_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create();
        $role = $this->role('Гэрээ хариуцсан');

        $this->saveUser($admin, $staff, ['role_key' => $role->key])->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', ['role' => $role->key]), [
                'label' => 'Гэрээ хариуцсан',
                'permissions' => ['contracts' => 'edit'],
            ])
            ->assertRedirect();

        $this->assertTrue(ModuleAccess::canEdit($staff->fresh(), 'contracts'));
    }

    public function test_clearing_the_role_is_saved_too(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $role = $this->role('Түр роль');
        $staff = User::factory()->create(['role_key' => $role->key]);

        $this->saveUser($admin, $staff, ['role_key' => null])->assertRedirect();

        $this->assertNull($staff->fresh()->role_key);
    }

    public function test_a_user_level_permission_still_overrides_the_role(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create();
        $role = $this->role('Хянагч', ['contracts' => 'view']);

        $this->saveUser($admin, $staff, [
            'role_key' => $role->key,
            'permissions' => ['contracts' => 'manage'],
        ])->assertRedirect();

        $staff = $staff->fresh();

        $this->assertTrue(ModuleAccess::canManage($staff, 'contracts'));
        $this->assertSame(
            'manage',
            UserModulePermission::query()
                ->where('user_id', $staff->id)
                ->where('module_key', 'contracts')
                ->value('level'),
        );
    }
}
