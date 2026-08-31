<?php

namespace App\Http\Controllers;

use App\Support\ModuleAccess;
use App\Support\ReportsCatalog;
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

        return Inertia::render('Modules/Reports/Index', [
            'title' => $config['title'] ?? 'Тайлан мэдээлэл',
            'subtitle' => $config['subtitle'] ?? null,
            'navigation' => ReportsCatalog::navigationTree(),
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

        return Inertia::render('Modules/Reports/Show', [
            'title' => $config['title'] ?? 'Тайлан мэдээлэл',
            'report' => [
                'key' => $item['key'],
                'number' => $item['number'] ?? null,
                'label' => $item['label'],
                'template' => $item['template'] ?? 'policy_tracking',
                'department' => $item['department'] ?? null,
                'section_key' => $item['section_key'] ?? null,
                'section_label' => $item['section_label'] ?? null,
                'columns' => $item['columns'] ?? [],
                'description' => $item['description'] ?? null,
            ],
            'navigation' => ReportsCatalog::navigationTree(),
            'canManage' => ModuleAccess::canManage($request->user(), 'reports'),
        ]);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(ModuleAccess::canView($request->user(), 'reports'), 403);
    }
}
