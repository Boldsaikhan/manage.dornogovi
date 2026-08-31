<?php

namespace App\Http\Controllers;

use App\Support\ModuleAccess;
use App\Support\ReportsCatalog;
use App\Support\ReportsData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeReports($request);

        $config = ReportsCatalog::config();
        $navigation = ReportsCatalog::navigationTree();
        $activeSection = ReportsCatalog::resolveSectionKey(
            $request->query('section'),
            $navigation[0]['key'] ?? null,
        );

        return Inertia::render('Modules/Reports/Index', [
            'title' => $config['title'] ?? 'Тайлан мэдээлэл',
            'subtitle' => $config['subtitle'] ?? null,
            'navigation' => $navigation,
            'activeSection' => $activeSection,
            'dashboard' => ReportsCatalog::dashboard(),
            'sources' => ReportsCatalog::sources(),
            'canManage' => ModuleAccess::canManage($request->user(), 'reports'),
        ]);
    }

    public function show(Request $request, string $report): Response
    {
        $this->authorizeReports($request);

        $item = ReportsCatalog::find($report);
        if (! $item) {
            throw new NotFoundHttpException();
        }

        $config = ReportsCatalog::config();
        $rows = ReportsData::rows($report);
        $dataMeta = ReportsData::meta($report);

        $reportPayload = [
            'key' => $item['key'],
            'number' => $item['number'] ?? null,
            'label' => $item['label'],
            'template' => $item['template'] ?? 'policy_tracking',
            'template_label' => $item['template_label'] ?? null,
            'department' => $item['department'] ?? null,
            'section_key' => $item['section_key'] ?? null,
            'section_label' => $item['section_label'] ?? null,
            'section_number' => $item['section_number'] ?? null,
            'columns' => $item['columns'] ?? [],
            'description' => $item['description'] ?? null,
            'source_file' => $item['source_file'] ?? null,
            'measures' => $item['measures'] ?? ($dataMeta['row_count'] ?? null),
            'progress' => $item['progress'] ?? null,
            'rows' => $rows,
        ];

        return Inertia::render('Modules/Reports/Show', [
            'title' => $config['title'] ?? 'Тайлан мэдээлэл',
            'period' => $config['period'] ?? null,
            'report' => $reportPayload,
            'navigation' => ReportsCatalog::navigationTree(),
            'canManage' => ModuleAccess::canManage($request->user(), 'reports'),
        ]);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(ModuleAccess::canView($request->user(), 'reports'), 403);
    }
}
