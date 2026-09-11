<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Захирамж, тушаалын таб бүрд эрхийг тусад нь тохируулах.
 */
class DecreeTabPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function decree(string $kind, string $number = '01'): Decree
    {
        return Decree::create([
            'category' => str_starts_with($kind, 'zahiramj') ? 'zahiramj' : 'tushaal',
            'kind' => $kind,
            'number' => $number,
            'title' => 'Туршилт '.$kind,
        ]);
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['is_specialist' => true]);

        foreach ($this->expandPermissions($permissions) as $key => $level) {
            UserModulePermission::create([
                'user_id' => $user->id,
                'module_key' => $key,
                'level' => $level,
            ]);
        }

        return $user->fresh();
    }

    public function test_a_sub_permission_overrides_the_module_level(): void
    {
        $user = $this->staff([
            'decrees' => 'view',
            'decrees:tushaal_a' => 'manage',
            'decrees:zahiramj_a' => 'view',
        ]);

        $this->assertTrue(ModuleAccess::canManage($user, 'decrees:tushaal_a'));
        $this->assertFalse(ModuleAccess::canManage($user, 'decrees:zahiramj_a'));

        // Тусгайлан заагаагүй таб нь модулийн эрхийг дагана.
        $this->assertTrue(ModuleAccess::canView($user, 'decrees:tushaal_b'));
        $this->assertFalse(ModuleAccess::canEdit($user, 'decrees:tushaal_b'));

        // «Хаалттай» гэж заасан таб нь модулийн эрхээс үл хамаарна.
        $closed = $this->staff([
            'decrees' => 'manage',
            'decrees:zahiramj_b' => 'closed',
        ]);

        $this->assertTrue(ModuleAccess::canManage($closed, 'decrees:zahiramj_a'));
        $this->assertFalse(ModuleAccess::canView($closed, 'decrees:zahiramj_b'));
    }

    public function test_only_permitted_tabs_are_listed(): void
    {
        // Бусад табыг тусгайлан хаана — эх модулийн эрхээс үл хамаарна.
        $user = $this->staff([
            'decrees:tushaal_a' => 'edit',
            'decrees:blank' => 'closed',
            'decrees:zahiramj_a' => 'closed',
            'decrees:zahiramj_b' => 'closed',
            'decrees:tushaal_b' => 'closed',
            'decrees:alban_daalgavar' => 'closed',
        ]);

        $this->decree('tushaal_a');
        $this->decree('zahiramj_a');

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'tushaal_a']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'tushaal_a')
                ->where('canEdit', true)
                // Тушаал А + Нийт (нийт нь зөвхөн эрхтэй төрлийг агуулна).
                ->has('tabs', 2)
                ->where('tabs.0.value', 'tushaal_a')
                ->where('tabs.1.value', 'niit')
                ->where('tabs.1.count', 1));

        // «Нийт» табд захирамж харагдахгүй.
        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'niit']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.kind', 'tushaal_a'));
    }

    public function test_a_closed_tab_redirects_to_an_allowed_one(): void
    {
        $user = $this->staff([
            'decrees' => 'view',
            'decrees:tushaal_a' => 'view',
            'decrees:blank' => 'closed',
            'decrees:zahiramj_a' => 'closed',
            'decrees:zahiramj_b' => 'closed',
            'decrees:tushaal_b' => 'closed',
            'decrees:alban_daalgavar' => 'closed',
        ]);

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'tushaal_a'));
    }

    public function test_a_user_cannot_edit_a_row_in_a_closed_tab(): void
    {
        $user = $this->staff([
            'decrees' => 'view',
            'decrees:tushaal_a' => 'edit',
            'decrees:zahiramj_a' => 'view',
        ]);

        $zahiramj = $this->decree('zahiramj_a');
        $tushaal = $this->decree('tushaal_a');

        $this->actingAs($user)
            ->patch(route('decrees.update', $zahiramj), ['title' => 'Өөрчилсөн'])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('decrees.update', $tushaal), ['title' => 'Өөрчилсөн'])
            ->assertRedirect();

        $this->assertSame('Туршилт zahiramj_a', $zahiramj->fresh()->title);
        $this->assertSame('Өөрчилсөн', $tushaal->fresh()->title);
    }

    public function test_a_role_template_can_carry_sub_permissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), ['label' => 'Тушаалын бүртгэгч'])
            ->assertRedirect();

        $role = Role::query()->where('label', 'Тушаалын бүртгэгч')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', ['role' => $role->key]), [
                'label' => 'Тушаалын бүртгэгч',
                'permissions' => [
                    'decrees' => 'view',
                    'decrees:tushaal_a' => 'manage',
                ],
            ])
            ->assertRedirect();

        $map = RolePermission::map()[$role->key] ?? [];

        $this->assertSame('view', $map['decrees'] ?? null);
        $this->assertSame('manage', $map['decrees:tushaal_a'] ?? null);
    }

    public function test_the_admin_page_lists_the_decree_sub_modules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $modules = collect($page->toArray()['props']['modules']);
                $keys = $modules->pluck('key')->all();

                $this->assertContains('decrees', $keys);
                $this->assertContains('decrees:zahiramj_a', $keys);
                $this->assertContains('decrees:alban_daalgavar', $keys);

                // Дэд мөр нь эх модулийнхоо шууд ард байрлана.
                $parentIndex = array_search('decrees', $keys, true);
                $this->assertSame('decrees:blank', $keys[$parentIndex + 1]);
            });
    }
}
