<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\ModuleOrder;
use App\Support\ModuleVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ModuleMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_module_is_hidden_from_nav_and_blocked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ModuleVisibility::setDisabled(['leaves']);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('moduleNav')
                ->where('moduleNav', function ($nav) {
                    $keys = collect($nav)->flatMap(fn ($g) => collect($g['items'])->pluck('key'));

                    return ! $keys->contains('leaves');
                })
            );
    }

    public function test_admin_can_toggle_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $enabled = ModuleAccess::definitions()
            ->mapWithKeys(fn (array $item) => [$item['key'] => $item['key'] !== 'leaves'])
            ->all();

        $this->actingAs($admin)
            ->patch(route('admin.menu-settings.update'), ['enabled' => $enabled])
            ->assertRedirect(route('admin.systems.index', ['tab' => 'menus']));

        $this->assertContains('leaves', ModuleVisibility::disabledKeys());
        $this->assertTrue(ModuleVisibility::isEnabled('tasks'));
    }

    public function test_nav_uses_saved_group_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ModuleOrder::setOrder(
            ['dashboard', 'work', 'documents', 'hr', 'knowledge', 'admin'],
            ModuleOrder::itemKeys(),
        );

        $keys = collect(ModuleAccess::navFor($admin))->pluck('key')->all();

        $this->assertSame('dashboard', $keys[0] ?? null);
        $this->assertSame('work', $keys[1] ?? null);
    }

    public function test_non_admin_sees_same_group_order_with_visible_items(): void
    {
        $specialist = User::factory()->create(['is_specialist' => true]);

        ModuleOrder::setOrder(
            ['dashboard', 'work', 'hr', 'documents', 'knowledge', 'admin'],
            ModuleOrder::itemKeys(),
        );

        $this->actingAs($specialist)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('moduleNav.0.key', 'dashboard')
                ->where('moduleNav.1.key', 'work')
            );
    }

    public function test_admin_can_reorder_menu_modules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $enabled = ModuleAccess::definitions()
            ->mapWithKeys(fn (array $item) => [$item['key'] => true])
            ->all();

        $itemOrder = ModuleOrder::itemKeys();
        $tasksIndex = array_search('tasks', $itemOrder, true);
        $workGroupsIndex = array_search('work_groups', $itemOrder, true);
        [$itemOrder[$tasksIndex], $itemOrder[$workGroupsIndex]] = [$itemOrder[$workGroupsIndex], $itemOrder[$tasksIndex]];

        $groupOrder = ['work', 'documents', 'hr', 'knowledge', 'admin', 'dashboard'];

        $this->actingAs($admin)
            ->patch(route('admin.menu-settings.update'), [
                'enabled' => $enabled,
                'group_order' => $groupOrder,
                'item_order' => $itemOrder,
            ])
            ->assertRedirect(route('admin.systems.index', ['tab' => 'menus']));

        $this->actingAs($admin)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('moduleNav.0.key', 'work')
                ->where('moduleNav.0.items.0.key', 'work_groups')
                ->where('moduleNav.1.key', 'documents')
            );
    }

    public function test_admin_menu_groups_reflect_saved_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $enabled = ModuleAccess::definitions()
            ->mapWithKeys(fn (array $item) => [$item['key'] => true])
            ->all();

        $itemOrder = ModuleOrder::itemKeys();
        $tasksIndex = array_search('tasks', $itemOrder, true);
        $workGroupsIndex = array_search('work_groups', $itemOrder, true);
        [$itemOrder[$tasksIndex], $itemOrder[$workGroupsIndex]] = [$itemOrder[$workGroupsIndex], $itemOrder[$tasksIndex]];

        ModuleOrder::setOrder(['work', 'documents', 'hr', 'knowledge', 'admin', 'dashboard'], $itemOrder);

        $this->actingAs($admin)
            ->get(route('admin.systems.index', ['tab' => 'menus']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('menuGroups.0.key', 'work')
                ->where('menuGroups.0.items.0.key', 'work_groups')
                ->where('menuGroups.1.key', 'documents')
            );
    }
}
