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
        System::create([
            'slug' => 'dotood',
            'name' => 'Дотоод дашбоард',
            'url' => 'https://example.mn',
            'category' => 'Хяналт',
            'is_internal' => true,
            'requires_login' => false,
        ]);

        System::create([
            'slug' => 'gadaad',
            'name' => 'Гадны систем',
            'url' => 'https://example.gov.mn',
            'category' => 'Санхүү',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Дараалал нь sort_order/нэрээр тодорхойлогддог — бүлэглэлт нь
                // хажуугийн цэсэнд хийгддэг тул зөвхөн тэмдэглэгээг шалгана.
                ->has('nav', 2)
                ->where('nav.0.name', 'Гадны систем')
                ->where('nav.0.is_internal', false)
                ->where('nav.1.name', 'Дотоод дашбоард')
                ->where('nav.1.is_internal', true)
                ->where('nav.1.requires_login', false)
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
