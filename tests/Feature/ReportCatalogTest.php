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

    public function test_reports_index_loads_catalog_with_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Index')
                ->has('navigation', 6)
                ->has('dashboard.kpis', 6)
                ->has('dashboard.sections', 6)
                ->has('sources', 10)
                ->where('title', 'Тайлан мэдээлэл'));
    }

    public function test_reports_show_loads_known_report_with_columns(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.governor_assignments.budget'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.key', 'local_policy.governor_assignments.budget')
                ->where('report.number', '2.7.4')
                ->where('report.source_file', '04-АЛБАН ДААЛГАВАР.docx')
                ->where('report.progress', 38)
                ->has('report.columns', 4));
    }

    public function test_reports_show_loads_excel_linked_report(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.law_implementation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.template', 'law_implementation')
                ->where('report.source_file', '1. Хууль тогтоомж 2026 оны эхний хагас жил.xlsx')
                ->has('report.columns', 8));
    }

    public function test_reports_show_404_for_unknown_key(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'missing.report'))
            ->assertNotFound();
    }
}
