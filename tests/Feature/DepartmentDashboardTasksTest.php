<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DepartmentDashboardTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_include_task_summary(): void
    {
        $user = User::factory()->create([
            'name' => 'Ариунболдын Бадрал',
            'is_admin' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/DepartmentDashboard')
                ->missing('recentTasks')
                ->missing('stats.task_total')
            );
    }
}
