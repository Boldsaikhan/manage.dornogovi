<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleAccess;
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
            ->assertRedirect();

        $this->assertContains('leaves', ModuleVisibility::disabledKeys());
        $this->assertTrue(ModuleVisibility::isEnabled('tasks'));
    }

    public function test_dashboard_group_is_last_in_sidebar_nav(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $keys = collect(ModuleAccess::navFor($admin))->pluck('key')->all();

        $this->assertContains('dashboard', $keys);
        $this->assertSame('dashboard', end($keys));
        $this->assertNotSame('dashboard', $keys[0] ?? null);
    }
}
