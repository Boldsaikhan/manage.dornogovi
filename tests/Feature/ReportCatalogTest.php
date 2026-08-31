<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_requires_auth(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_reports_index_loads_catalog(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Index')
                ->has('navigation', 6)
                ->where('title', 'Тайлан мэдээлэл'));
    }

    public function test_reports_show_loads_known_report(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.annual_plan'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.key', 'local_policy.annual_plan')
                ->where('report.number', '2.2'));
    }

    public function test_reports_show_404_for_unknown_key(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'missing.report'))
            ->assertNotFound();
    }
}
