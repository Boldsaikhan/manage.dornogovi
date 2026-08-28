<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SidebarGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_marks_internal_systems(): void
    {
        $dotood = System::create([
            'slug' => 'dotood',
            'name' => 'Дотоод дашбоард',
            'url' => 'https://example.mn',
            'category' => 'Хяналт',
            'is_internal' => true,
            'requires_login' => false,
            'sort_order' => 2,
        ]);

        $gadaad = System::create([
            'slug' => 'gadaad',
            'name' => 'Гадны систем',
            'url' => 'https://example.gov.mn',
            'category' => 'Санхүү',
            'sort_order' => 1,
        ]);

        $staff = User::factory()->create();
        $dotood->viewers()->sync([$staff->id]);
        $gadaad->viewers()->sync([$staff->id]);

        $this->actingAs($staff)
            ->get(route('dept.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('nav', 1)
                ->where('nav.0.name', 'Гадны систем')
                ->where('nav.0.is_internal', false)
            );
    }

    public function test_admin_can_move_a_system_into_the_internal_group(): void
    {
        $system = System::create([
            'slug' => 'gadaad',
            'name' => 'Гадны систем',
            'url' => 'https://example.gov.mn',
            'category' => 'Санхүү',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch(route('admin.systems.update', $system), [
                'name' => $system->name,
                'url' => $system->url,
                'login_method' => System::LOGIN_MANUAL,
                'is_active' => true,
                'requires_login' => true,
                'is_internal' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($system->fresh()->is_internal);
    }
}
