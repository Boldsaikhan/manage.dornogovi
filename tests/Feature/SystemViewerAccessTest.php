<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function system(string $slug = 'shilen'): System
    {
        return System::create([
            'slug' => $slug,
            'name' => 'Шилэн данс',
            'url' => 'https://shilen.gov.mn/home',
            'category' => 'Санхүү',
            'is_active' => true,
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    public function test_system_without_viewers_is_hidden_from_staff_menu(): void
    {
        $this->system();

        $this->actingAs($this->staff())
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('nav', 0));
    }

    public function test_system_is_hidden_from_users_who_were_not_added(): void
    {
        $system = $this->system();
        $system->viewers()->sync([User::factory()->create()->id]);

        $outsider = $this->staff();

        $this->actingAs($outsider)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('nav', 0));

        $this->actingAs($outsider)
            ->get(route('systems.show', $system))
            ->assertForbidden();
    }

    public function test_added_employee_sees_the_system(): void
    {
        $system = $this->system();
        $user = $this->staff();
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('nav', 1));

        $this->actingAs($user)
            ->get(route('systems.show', $system))
            ->assertOk();
    }

    public function test_internal_systems_are_not_listed_in_staff_menu(): void
    {
        $user = $this->staff();
        $system = System::create([
            'slug' => 'dotood',
            'name' => 'Дотоод',
            'url' => 'https://example.mn',
            'is_active' => true,
            'is_internal' => true,
        ]);
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('nav', 0));
    }

    public function test_admin_saves_the_viewer_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $system = $this->system();
        $employee = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.systems.update', $system), [
                'name' => $system->name,
                'url' => $system->url,
                'login_method' => System::LOGIN_MANUAL,
                'is_active' => true,
                'requires_login' => true,
                'is_internal' => false,
                'viewer_ids' => [$employee->id],
            ])
            ->assertRedirect();

        $this->assertSame([$employee->id], $system->fresh()->viewers->pluck('id')->all());
    }

    public function test_admin_can_assign_viewers_without_full_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $system = $this->system();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.systems.viewers', $system), [
                'viewer_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('admin.systems.index', [
                'tab' => 'systems',
                'system' => $system->id,
            ]))
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $system->fresh()->viewers->pluck('id')->all());
    }

    public function test_admin_always_can_open_every_system(): void
    {
        $system = $this->system();
        $system->viewers()->sync([User::factory()->create()->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('systems.show', $system))
            ->assertOk();
    }
}
