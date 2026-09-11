<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Татах, файлаас оруулах, хэвлэхийг ролийн эрхээр тусад нь зөвшөөрөх.
 */
class DecreeActionPermissionTest extends TestCase
{
    use RefreshDatabase;

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

    private function decree(): Decree
    {
        return Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Туршилт',
        ]);
    }

    public function test_by_default_the_actions_follow_the_module_permission(): void
    {
        $user = $this->staff(['decrees' => 'edit']);
        $this->decree();

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canExport', true)
                ->where('canPrint', true)
                ->where('canImportFile', true));

        $this->actingAs($user)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'xlsx']))
            ->assertOk();
    }

    public function test_export_can_be_closed_on_its_own(): void
    {
        $user = $this->staff([
            'decrees' => 'edit',
            'decrees:export' => 'closed',
        ]);
        $this->decree();

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canExport', false)
                // Бусад үйлдэл хэвээрээ.
                ->where('canPrint', true)
                ->where('canImportFile', true));

        $this->actingAs($user)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'xlsx']))
            ->assertForbidden();
    }

    public function test_import_can_be_closed_on_its_own(): void
    {
        $user = $this->staff([
            'decrees' => 'edit',
            'decrees:import' => 'closed',
        ]);

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canImportFile', false)
                ->where('canExport', true));

        $this->actingAs($user)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [['number' => '05', 'title' => 'Оролдлого']],
            ])
            ->assertForbidden();

        $this->assertSame(0, Decree::query()->count());
    }

    public function test_print_can_be_closed_on_its_own(): void
    {
        $user = $this->staff([
            'decrees' => 'view',
            'decrees:print' => 'closed',
        ]);
        $this->decree();

        $this->actingAs($user)
            ->get(route('decrees.print', ['tab' => 'zahiramj_a']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'pdf']))
            ->assertOk();
    }

    public function test_a_viewer_cannot_import_even_when_allowed_to(): void
    {
        // «Файлаас оруулах»-ыг зөвшөөрсөн ч модульд оруулах эрхгүй бол болохгүй.
        $user = $this->staff([
            'decrees' => 'view',
            'decrees:import' => 'manage',
        ]);

        $this->actingAs($user)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canImportFile', false));
    }

    public function test_the_actions_are_listed_in_the_permission_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $keys = collect($page->toArray()['props']['modules'])->pluck('key')->all();

                $this->assertContains('decrees:export', $keys);
                $this->assertContains('decrees:import', $keys);
                $this->assertContains('decrees:print', $keys);
            });
    }
}
