<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Дэд эрх нь тохируулаагүй бол хаалттай.
 */
class SubPermissionDefaultClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_role_starts_fully_closed(): void
    {
        $staff = User::factory()->create(['is_admin' => false]);

        // Шинэ роль — юу ч тохируулаагүй.
        RolePermission::replaceFor('shine_rol', []);
        $staff->forceFill(['role_key' => 'shine_rol'])->save();

        $staff = $staff->fresh();

        foreach (ModuleAccess::SUB_MODULES as $parent => $subs) {
            foreach (array_keys($subs) as $sub) {
                $this->assertFalse(
                    ModuleAccess::canView($staff, $parent.':'.$sub),
                    $parent.':'.$sub.' хаалттай байх ёстой.',
                );
            }
        }
    }

    public function test_opening_the_parent_does_not_open_the_sub_rows(): void
    {
        $staff = User::factory()->create(['is_admin' => false]);

        UserModulePermission::create([
            'user_id' => $staff->id,
            'module_key' => 'decrees',
            'level' => 'manage',
        ]);

        $staff = $staff->fresh();

        $this->assertTrue(ModuleAccess::canManage($staff, 'decrees'));
        $this->assertFalse(ModuleAccess::canView($staff, 'decrees:zahiramj_a'));
        $this->assertFalse(ModuleAccess::canView($staff, 'decrees:export'));
    }

    public function test_a_sub_row_opened_on_its_own_works(): void
    {
        $staff = User::factory()->create(['is_admin' => false]);

        foreach ([['decrees', 'view'], ['decrees:tushaal_a', 'manage']] as [$key, $level]) {
            UserModulePermission::create([
                'user_id' => $staff->id,
                'module_key' => $key,
                'level' => $level,
            ]);
        }

        $staff = $staff->fresh();

        $this->assertTrue(ModuleAccess::canManage($staff, 'decrees:tushaal_a'));
        $this->assertFalse(ModuleAccess::canView($staff, 'decrees:tushaal_b'));
    }

    public function test_the_permission_table_no_longer_offers_inheriting(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            // Хуудсанд «дагах» гэсэн сонголт байхгүй.
            ->assertDontSee('Дээд мөрийг дагах');
    }

    public function test_the_migration_keeps_what_roles_already_had(): void
    {
        // Хуучин байдал: зөвхөн эцэг модуль тохируулсан роль.
        DB::table('role_permissions')->insert([
            ['role' => 'huuchin', 'module_key' => 'decrees', 'level' => 'edit'],
            ['role' => 'huuchin', 'module_key' => 'decrees:export', 'level' => 'closed'],
            ['role' => 'haalttai', 'module_key' => 'decrees', 'level' => 'closed'],
        ]);

        $this->runMigration();

        $rows = DB::table('role_permissions')
            ->where('role', 'huuchin')
            ->pluck('level', 'module_key');

        // Өмнө нь өвлөж байсан дэд мөрүүд нь тодорхой бичигдэнэ.
        $this->assertSame('edit', $rows['decrees:zahiramj_a'] ?? null);
        $this->assertSame('edit', $rows['decrees:print'] ?? null);
        // Тусгайлан хаасан нь хэвээрээ.
        $this->assertSame('closed', $rows['decrees:export'] ?? null);

        // Эцэг нь хаалттай ролид дэд мөр нэмэгдэхгүй.
        $this->assertSame(
            1,
            DB::table('role_permissions')->where('role', 'haalttai')->count(),
        );
    }

    private function runMigration(): void
    {
        $path = database_path('migrations/2026_09_11_210000_materialise_inherited_sub_permissions.php');

        (require $path)->up();
    }
}
