<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ReportRows;
use App\Support\XlsxTableWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                ->has('navigation', 4)
                ->where('navigation.0.label', 'Төрийн бодлого')
                ->where('navigation.1.label', 'Орон нутгийн бодлого')
                ->where('navigation.2.label', 'Гэрээ')
                ->where('navigation.3.label', 'Зорилтот хөтөлбөр')
                ->where('activeSection', 'state_policy')
                ->has('dashboard.kpis', 4)
                ->has('dashboard.sections', 4)
                ->has('sources', 10)
                ->where('title', 'Тайлан мэдээлэл'));

        $this->actingAs($user)
            ->get(route('reports.index', ['section' => 'contracts']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeSection', 'contracts'));
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
                ->has('report.columns', 6)
                ->where('report.columns.4.key', 'department')
                ->where('report.columns.5.key', 'assignee'));
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
                ->has('report.columns', 10));
    }

    public function test_reports_show_loads_governor_program_rows(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.governor_program'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.key', 'local_policy.governor_program')
                ->where('report.number', '2.1')
                ->where('report.source_file', 'АЗДҮАХ-2024-2028-2026-оны-хагас-жил-ТАЙЛАН-хуваарилалт.xlsx')
                ->where('report.template', 'governor_program')
                ->has('report.columns', 17)
                ->has('report.rows', 324));
    }

    public function test_reports_show_loads_annual_plan_rows(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.annual_plan'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.key', 'local_policy.annual_plan')
                ->where('report.number', '2.2')
                ->where('report.source_file', 'АЖХТ-2026-ЭЦЭС.xlsx')
                ->where('report.template', 'annual_plan')
                ->has('report.columns', 13)
                ->has('report.rows', 196));
    }

    public function test_reports_show_loads_council_decision_rows(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'local_policy.council_decision'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Reports/Show')
                ->where('report.key', 'local_policy.council_decision')
                ->where('report.number', '2.3')
                ->where('report.source_file', 'ИТХТ-ын хэрэгжилт_2026_.xlsx')
                ->where('report.template', 'itkh_decision')
                ->has('report.columns', 9)
                ->has('report.rows', 33));
    }

    public function test_reports_show_404_for_unknown_key(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'missing.report'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('reports.show', 'memorandum.summary'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('reports.show', 'investment.summary'))
            ->assertNotFound();
    }

    public function test_report_word_export_downloads_docx(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user)
            ->get(route('reports.export', 'local_policy.governor_program'));

        $response->assertOk();
        $this->assertSame('PK', substr($response->getContent(), 0, 2));
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'filename*=UTF-8\'\'',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_report_export_downloads_excel_and_pdf(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $excel = $this->actingAs($user)
            ->get(route('reports.export', ['report' => 'contracts.agency_directors', 'format' => 'xlsx']));
        $excel->assertOk();
        $this->assertSame('PK', substr($excel->getContent(), 0, 2));
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $excel->headers->get('content-type'),
        );

        $pdf = $this->actingAs($user)
            ->get(route('reports.export', ['report' => 'contracts.agency_directors', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($user)
            ->get(route('reports.export', ['report' => 'contracts.agency_directors', 'format' => 'csv']))
            ->assertNotFound();
    }

    public function test_report_import_maps_headers_then_appends_rows(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $path = storage_path('framework/testing/report-import.xlsx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        app(XlsxTableWriter::class)->write($path, 'гэрээ', ['№', 'Бодлогын баримт бичиг', 'Тоо хэмжээ'], [
            ['1', 'Туршилтын баримт', '4'],
        ]);

        $file = new UploadedFile(
            $path,
            'geree.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $preview = $this->actingAs($user)
            ->post(route('reports.import.preview', 'contracts.agency_directors'), ['file' => $file]);

        $preview->assertOk();
        $preview->assertJsonPath('mapping.document', 1);
        $preview->assertJsonPath('mapping.count', 2);
        $preview->assertJsonPath('total', 1);
        $preview->assertJsonPath('entries.0.document', 'Туршилтын баримт');

        $this->actingAs($user)
            ->post(route('reports.import.store', 'contracts.agency_directors'), [
                'entries' => $preview->json('entries'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '1 мөр нэмэгдлээ.');

        $rows = ReportRows::merged('contracts.agency_directors');
        $this->assertCount(1, $rows);
        $this->assertSame('Туршилтын баримт', $rows[0]['document']);
        $this->assertSame('4', $rows[0]['count']);

        $this->actingAs($user)
            ->patch(route('reports.rows.update', ['report' => 'contracts.ministry_gbxn', 'index' => 2]), [
                'column' => '_span',
                'value' => '0',
            ])
            ->assertRedirect();

        $unmerged = ReportRows::merged('contracts.ministry_gbxn');
        $this->assertSame('3.1.2', $unmerged[2]['no']);
        $this->assertFalse($unmerged[2]['_span']);

        $viewer = User::factory()->create();
        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'reports',
            'level' => 'view',
        ]);

        $this->actingAs($viewer)
            ->post(route('reports.import.preview', 'contracts.agency_directors'), ['file' => $file])
            ->assertForbidden();

        @unlink($path);
    }

    public function test_contract_docx_rows_are_in_the_catalog(): void
    {
        $health = ReportRows::merged('contracts.ministry_health');
        $this->assertGreaterThan(100, count($health));
        $this->assertSame('1', $health[0]['no']);
        $this->assertStringContainsString('Артерийн', $health[0]['goal']);

        $gbxn = ReportRows::merged('contracts.ministry_gbxn');
        $this->assertGreaterThan(20, count($gbxn));
        $this->assertStringContainsString('Гэр бүлийн', $gbxn[0]['document']);
        $this->assertTrue($gbxn[0]['_span']);

        $section = collect($gbxn)->first(fn (array $row) => ($row['no'] ?? '') === '3.1.2');
        $this->assertNotNull($section);
        $this->assertTrue($section['_span']);
        $this->assertStringContainsString('хүчирхийлэлтэй тэмцэх талаар', $section['_span_text']);

        $detail = collect($gbxn)->first(fn (array $row) => str_contains((string) ($row['no'] ?? ''), '1.2.1'));
        $this->assertNotNull($detail);
        $this->assertFalse($detail['_span']);

        foreach ([
            'contracts.ministry_zgheg',
            'contracts.deputy_dorjhand',
            'contracts.deputy_gankhuyag',
            'contracts.ministry_digital',
            'contracts.ministry_foreign',
            'contracts.ministry_finance',
            'contracts.ministry_defense',
            'contracts.ministry_legal',
            'contracts.ministry_economy',
            'contracts.ministry_environment',
            'contracts.ministry_mram',
            'contracts.ministry_urban',
            'contracts.ministry_food',
            'contracts.ministry_energy',
            'contracts.ministry_road',
            'contracts.ministry_education',
            'contracts.ministry_culture',
        ] as $key) {
            $this->assertGreaterThan(0, count(ReportRows::merged($key)), $key);
        }
    }

    public function test_report_word_export_404_for_unknown_key(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.export', 'missing.report'))
            ->assertNotFound();
    }

    public function test_report_word_export_requires_auth(): void
    {
        $this->get(route('reports.export', 'local_policy.governor_program'))
            ->assertRedirect(route('login'));
    }

    public function test_contract_pdfs_are_attached_and_under_five_megabytes(): void
    {
        $keys = [
            'contracts.ministry_gbxn',
            'contracts.ministry_culture',
            'contracts.ministry_health',
            'contracts.ministry_education',
            'contracts.ministry_defense',
            'contracts.ministry_finance',
            'contracts.ministry_zgheg',
            'contracts.deputy_gankhuyag',
            'contracts.ministry_digital',
            'contracts.ministry_foreign',
            'contracts.deputy_dorjhand',
            'contracts.ministry_legal',
            'contracts.ministry_economy',
            'contracts.ministry_environment',
            'contracts.ministry_mram',
            'contracts.ministry_urban',
            'contracts.ministry_food',
            'contracts.ministry_energy',
            'contracts.ministry_road',
        ];

        foreach ($keys as $key) {
            $path = base_path('database/data/reports/contracts/'.$key.'.pdf');
            $this->assertFileExists($path, $key);
            $this->assertLessThanOrEqual(5 * 1024 * 1024, filesize($path), $key);
            $this->assertSame('%PDF', file_get_contents($path, false, null, 0, 4), $key);
        }

        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'contracts.ministry_gbxn'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.key', 'contracts.ministry_gbxn')
                ->where('report.number', '3.1')
                ->where('report.pdf_url', route('reports.file', 'contracts.ministry_gbxn')));

        $response = $this->actingAs($user)
            ->get(route('reports.file', 'contracts.ministry_culture'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertLessThanOrEqual(5 * 1024 * 1024, (int) $response->headers->get('content-length'));
        $this->assertGreaterThan(1000, (int) $response->headers->get('content-length'));
    }

    public function test_digital_dornogovi_program_pdf_is_attached(): void
    {
        $path = base_path('database/data/reports/pdfs/target_programs.digital_dornogovi.pdf');
        $this->assertFileExists($path);
        $this->assertLessThanOrEqual(5 * 1024 * 1024, filesize($path));
        $this->assertSame('%PDF', file_get_contents($path, false, null, 0, 4));

        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.show', 'target_programs.digital_dornogovi'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.key', 'target_programs.digital_dornogovi')
                ->where('report.number', '4.5')
                ->where('report.label', 'Цахим Дорноговь')
                ->where('report.pdf_url', route('reports.file', 'target_programs.digital_dornogovi')));
    }

    public function test_report_without_pdf_file_is_not_found(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('reports.file', 'local_policy.governor_program'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('reports.file', 'missing.report'))
            ->assertNotFound();
    }

    public function test_contract_pdf_requires_auth(): void
    {
        $this->get(route('reports.file', 'contracts.ministry_gbxn'))
            ->assertRedirect(route('login'));
    }
}
