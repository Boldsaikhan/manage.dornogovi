<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ReportRowEdit;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\ReportRows;
use App\Support\ReportsCatalog;
use App\Support\ReportsData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;
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
        $rows = ReportRows::visibleTo(ReportRows::merged($report), $request->user());
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
            'canEdit' => ModuleAccess::canEdit($request->user(), 'reports'),
            // «Хэлтэс» баганад сонгох жагсаалт.
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'departmentColumn' => ReportRowEdit::DEPARTMENT_COLUMN,
        ]);
    }

    public function export(Request $request, string $report, DocxTableWriter $docx): HttpResponse
    {
        $this->authorizeReports($request);

        $item = ReportsCatalog::find($report);
        if (! $item) {
            throw new NotFoundHttpException();
        }

        $columns = $item['columns'] ?? [];
        abort_if($columns === [], 404, 'Хүснэгтийн бүтэц тохируулаагүй байна.');

        $rows = ReportRows::visibleTo(ReportRows::merged($report), $request->user());
        $title = trim((string) ($item['description'] ?? '')) ?: trim(
            trim((string) ($item['number'] ?? '')).' '.(string) ($item['label'] ?? 'Тайлан')
        );
        $payload = $this->exportTable($title, $columns, $rows);
        $tmp = tempnam(sys_get_temp_dir(), 'report_export_');

        try {
            $path = $tmp.'.docx';
            $docx->write(
                $path,
                $payload['title'],
                $payload['headings'],
                $payload['widths'],
                $payload['docx_rows'],
                $payload['center'],
                $payload['landscape'],
            );
            $content = (string) file_get_contents($path);
            @unlink($path);
        } finally {
            @unlink($tmp);
        }

        $fileName = $title.' '.now()->format('Y-m-d').'.docx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"report.docx\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /**
     * @param  list<array{key?: string, label?: string, width?: string|null}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     title: string,
     *     headings: list<string>,
     *     widths: list<int>,
     *     center: list<int>,
     *     landscape: bool,
     *     docx_rows: list<array{type: string, cells: list<string>}>
     * }
     */
    /**
     * Мөрийн нэг нүдийг засна.
     */
    public function updateRow(Request $request, string $report, int $index): RedirectResponse
    {
        $this->authorizeReports($request);

        abort_unless(ModuleAccess::canEdit($request->user(), 'reports'), 403);

        $item = ReportsCatalog::find($report);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        $columns = collect($item['columns'] ?? [])->pluck('key')->all();

        $data = $request->validate([
            'column' => ['required', 'string', Rule::in($columns)],
            'value' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        abort_if($index < 0 || $index >= count(ReportsData::rows($report)), 404);

        $isDepartment = $data['column'] === ReportRowEdit::DEPARTMENT_COLUMN;

        ReportRowEdit::updateOrCreate(
            [
                'report_key' => $report,
                'row_index' => $index,
                'column_key' => $data['column'],
            ],
            [
                'value' => $isDepartment ? null : ($data['value'] ?? null),
                'department_id' => $isDepartment ? ($data['department_id'] ?? null) : null,
                'updated_by' => $request->user()->id,
            ],
        );

        return back(303);
    }

    private function exportTable(string $title, array $columns, array $rows): array
    {
        $headings = array_map(
            fn (array $col) => (string) ($col['label'] ?? $col['key'] ?? ''),
            $columns,
        );
        $centerKeys = ['no', 'percent', 'count', 'unit', 'baseline', 'target', 'progress'];
        $center = [];
        $widths = [];

        foreach ($columns as $i => $column) {
            $widths[] = $this->columnTwip($column);
            if (in_array((string) ($column['key'] ?? ''), $centerKeys, true)) {
                $center[] = $i;
            }
        }

        $docxRows = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                $value = $row[$key] ?? '';
                $cells[] = is_scalar($value) || $value === null ? (string) $value : '';
            }
            $docxRows[] = ['type' => 'data', 'cells' => $cells];
        }

        return [
            'title' => $title,
            'headings' => $headings,
            'widths' => $widths,
            'center' => $center,
            'landscape' => count($columns) > 6,
            'docx_rows' => $docxRows,
        ];
    }

    /**
     * @param  array{key?: string, width?: string|null}  $column
     */
    private function columnTwip(array $column): int
    {
        $width = $column['width'] ?? null;
        if (is_string($width) && preg_match('/^([\d.]+)rem$/', $width, $match)) {
            return max(700, (int) round((float) $match[1] * 16 * 15));
        }

        $defaults = [
            'activity' => 2800,
            'measure' => 2800,
            'indicator' => 2000,
            'goal' => 2400,
            'clause_text' => 2800,
            'decision_title' => 2600,
            'action_plan' => 2600,
            'note' => 1800,
            'no' => 700,
        ];

        return $defaults[(string) ($column['key'] ?? '')] ?? 1400;
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(ModuleAccess::canView($request->user(), 'reports'), 403);
    }
}
