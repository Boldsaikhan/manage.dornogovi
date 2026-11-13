<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PhoneDirectoryEntry;
use App\Models\ReportRowApproval;
use App\Models\ReportRowEdit;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\PdfTableWriter;
use App\Support\ReportFormula;
use App\Support\ReportRows;
use App\Support\ReportWorkflow;
use App\Support\ReportSpan;
use App\Support\ReportTableImporter;
use App\Support\ReportsCatalog;
use App\Support\ReportsData;
use App\Support\TabularFileReader;
use App\Support\XlsxTableWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $template = ReportsCatalog::template($item['template'] ?? 'policy_tracking');
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
            'columns' => self::visibleColumns($item['columns'] ?? [], $request->user()),
            'description' => $item['description'] ?? null,
            'source_file' => $item['source_file'] ?? null,
            'pdf_url' => ReportsCatalog::pdfPath($item['pdf'] ?? null)
                ? route('reports.file', $item['key'])
                : null,
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
            'people' => PhoneDirectoryEntry::peopleOptions(),
            // «Удирдах (хамааралтай / хэлтсийн)» эрхтэй хүн өөрийн хэлтсийн
            // албан хаагчийг мөр бүрт хуваарилна.
            'unitScope' => self::unitScoped($request->user()),
            'ownDepartment' => $request->user()?->department?->name,
            'workflow' => [
                'approval' => ReportWorkflow::needsApproval($template),
                'schedule' => ReportWorkflow::hasSchedule($template),
                'monitor_columns' => ReportWorkflow::monitorColumns($template),
                'formula' => ReportWorkflow::formulaMap($template),
                'formula_options' => ReportFormula::options(),
                'can_approve' => ReportWorkflow::canApprove($request->user()),
                'can_monitor' => ReportWorkflow::canMonitor($request->user()),
                'approvals' => ReportWorkflow::approvals($report),
                'due' => ReportWorkflow::schedule($report),
            ],
        ]);
    }

    public function export(
        Request $request,
        string $report,
        DocxTableWriter $docx,
        XlsxTableWriter $xlsx,
        PdfTableWriter $pdf,
    ): HttpResponse {
        $this->authorizeReports($request);

        $format = strtolower((string) $request->query('format', 'docx'));
        abort_unless(in_array($format, ['docx', 'xlsx', 'pdf'], true), 404);

        $item = ReportsCatalog::find($report);
        if (! $item) {
            throw new NotFoundHttpException();
        }

        $columns = self::visibleColumns($item['columns'] ?? [], $request->user());
        abort_if($columns === [], 404, 'Хүснэгтийн бүтэц тохируулаагүй байна.');

        $rows = ReportRows::visibleTo(ReportRows::merged($report), $request->user());
        $title = trim((string) ($item['description'] ?? '')) ?: trim(
            trim((string) ($item['number'] ?? '')).' '.(string) ($item['label'] ?? 'Тайлан')
        );
        $payload = $this->exportTable($title, $columns, $rows);
        $tmp = tempnam(sys_get_temp_dir(), 'report_export_');

        try {
            if ($format === 'xlsx') {
                $path = $tmp.'.xlsx';
                $xlsx->write($path, $payload['title'], $payload['headings'], $payload['sheet_rows']);
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $ascii = 'report.xlsx';
            } elseif ($format === 'pdf') {
                $path = $tmp.'.pdf';
                $pdf->write($path, $payload['title'], $payload['headings'], $payload['sheet_rows'], $payload['landscape']);
                $mime = 'application/pdf';
                $ascii = 'report.pdf';
            } else {
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
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $ascii = 'report.docx';
            }
            $content = (string) file_get_contents($path);
            @unlink($path);
        } finally {
            @unlink($tmp);
        }

        $fileName = $title.' '.now()->format('Y-m-d').'.'.$format;

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    public function file(Request $request, string $report): BinaryFileResponse
    {
        $this->authorizeReports($request);

        $item = ReportsCatalog::find($report);
        $path = $item ? ReportsCatalog::pdfPath($item['pdf'] ?? null) : null;

        if (! $path) {
            throw new NotFoundHttpException();
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
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
     *     docx_rows: list<array{type: string, cells: list<string>}>,
     *     sheet_rows: list<list<string>>
     * }
     */
    /**
     * Excel, Word, PDF-ээс уншиж, баганыг тааруулахын өмнө харуулна.
     */
    public function importPreview(
        Request $request,
        string $report,
        TabularFileReader $reader,
        ReportTableImporter $importer,
    ): JsonResponse {
        $this->authorizeReports($request);
        abort_unless(ModuleAccess::canEdit($request->user(), 'reports'), 403);

        $item = ReportsCatalog::find($report);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        $columns = $item['columns'] ?? [];
        abort_if($columns === [], 404, 'Хүснэгтийн бүтэц тохируулаагүй байна.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', TabularFileReader::EXTENSIONS), 'max:20480'],
        ], [], ['file' => 'файл']);

        $file = $request->file('file');
        $rows = $reader->rows($file->getRealPath(), (string) $file->getClientOriginalExtension());

        return response()->json($importer->preview($rows, $columns));
    }

    /**
     * Тааруулсан мөрүүдийг одоогийн хүснэгтийн ард нэмнэ.
     */
    public function importStore(Request $request, string $report, ReportTableImporter $importer): RedirectResponse
    {
        $this->authorizeReports($request);
        abort_unless(ModuleAccess::canEdit($request->user(), 'reports'), 403);

        $item = ReportsCatalog::find($report);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        $columns = $item['columns'] ?? [];
        abort_if($columns === [], 404, 'Хүснэгтийн бүтэц тохируулаагүй байна.');

        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:400'],
            'entries.*' => ['array'],
        ]);

        $created = $importer->store($report, $data['entries'], $columns, $request->user()->id);

        return back()->with(
            'success',
            $created > 0 ? $created.' мөр нэмэгдлээ.' : 'Оруулах мөр олдсонгүй.',
        );
    }

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
        $columns[] = ReportSpan::COLUMN;

        $data = $request->validate([
            'column' => ['required', 'string', Rule::in($columns)],
            'value' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        if ($data['column'] === ReportSpan::COLUMN) {
            $data['value'] = ($data['value'] ?? '') === '1' ? '1' : '0';
        }

        abort_if($index < 0 || $index >= ReportRows::length($report), 404);

        $template = ReportsCatalog::template($item['template'] ?? 'policy_tracking');

        // Хяналтын үнэлгээг зөвхөн хяналт-шинжилгээний хүн бөглөнө.
        if (in_array($data['column'], ReportWorkflow::monitorColumns($template), true)) {
            abort_unless(ReportWorkflow::canMonitor($request->user()), 403);

            // Баталгаажаагүй мөр хяналт-шинжилгээнд очоогүй байна.
            abort_unless(
                ReportRowApproval::query()
                    ->where('report_key', $report)
                    ->where('row_index', $index)
                    ->exists(),
                422,
                'Хэлтсийн дарга баталгаажуулаагүй байна.',
            );
        }

        $isDepartment = $data['column'] === ReportRowEdit::DEPARTMENT_COLUMN;

        // Хэлтэс хуваарилалтыг зөвхөн бүтэн удирдлага хийнэ.
        if ($isDepartment) {
            abort_unless(self::canAssignDepartments($request->user()), 403);
        }

        // Агентлаг багана мөн хуваарилалтын мэдээлэл — хэлтсийн хүрээнд засахгүй.
        if ($data['column'] === 'agency' && ! self::canAssignDepartments($request->user())) {
            abort(403);
        }

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

        $this->recalculatePercent($request, $report, $index, $template, $data['column']);

        return back(303);
    }

    /**
     * Зорилт, гүйцэтгэл, томъёоны аль нэг нь өөрчлөгдвөл хувийг дахин бодно.
     *
     * @param  array<string, mixed>|null  $template
     */
    private function recalculatePercent(
        Request $request,
        string $report,
        int $index,
        ?array $template,
        string $changed,
    ): void {
        $map = ReportWorkflow::formulaMap($template);

        if (! $map) {
            return;
        }

        $inputs = array_filter([
            $map['column'],
            $map['target'],
            $map['actual'],
            $map['baseline'] ?? null,
        ]);

        if (! in_array($changed, $inputs, true)) {
            return;
        }

        $row = ReportRows::merged($report)[$index] ?? [];
        $formula = (string) ($row[$map['column']] ?? '');
        $percent = ReportFormula::percent(
            $formula,
            $row[$map['actual']] ?? null,
            $row[$map['target']] ?? null,
            isset($map['baseline']) ? ($row[$map['baseline']] ?? null) : null,
        );

        // Томъёогүй бол гараар бичсэн утгыг хэвээр үлдээнэ.
        if ($percent === null && $formula === ReportFormula::MANUAL) {
            return;
        }

        ReportRowEdit::updateOrCreate(
            [
                'report_key' => $report,
                'row_index' => $index,
                'column_key' => $map['percent'],
            ],
            [
                'value' => $percent === null ? null : rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%',
                'department_id' => null,
                'updated_by' => $request->user()->id,
            ],
        );
    }

    /** Мөрийг хэлтсийн дарга баталгаажуулна / буцаана. */
    public function approveRow(Request $request, string $report, int $index): RedirectResponse
    {
        $this->authorizeReports($request);

        $item = ReportsCatalog::find($report);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        $template = ReportsCatalog::template($item['template'] ?? 'policy_tracking');

        abort_unless(ReportWorkflow::needsApproval($template), 404);
        abort_unless(ReportWorkflow::canApprove($request->user()), 403);
        abort_if($index < 0 || $index >= ReportRows::length($report), 404);

        $approved = $request->boolean('approved', true);

        if (! $approved) {
            ReportRowApproval::query()
                ->where('report_key', $report)
                ->where('row_index', $index)
                ->delete();

            return back(303)->with('success', 'Баталгаажуулалтыг буцаалаа.');
        }

        ReportRowApproval::updateOrCreate(
            ['report_key' => $report, 'row_index' => $index],
            ['approved_by' => $request->user()->id, 'approved_at' => now()],
        );

        return back(303)->with('success', 'Мөрийг баталгаажууллаа.');
    }

    /** Гэрээ ирүүлэх хугацаа. */
    public function updateSchedule(Request $request, string $report): RedirectResponse
    {
        $this->authorizeReports($request);

        $item = ReportsCatalog::find($report);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        $template = ReportsCatalog::template($item['template'] ?? 'policy_tracking');

        abort_unless(ReportWorkflow::hasSchedule($template), 404);
        abort_unless(ModuleAccess::canManage($request->user(), 'reports'), 403);

        $data = $request->validate([
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        ReportSchedule::updateOrCreate(
            ['report_key' => $report],
            [
                'due_on' => $data['due_on'] ?? null,
                'note' => $data['note'] ?? null,
                'updated_by' => $request->user()->id,
            ],
        );

        return back(303)->with('success', 'Гэрээ ирүүлэх хугацааг хадгаллаа.');
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
            if (! empty($row[ReportSpan::COLUMN])) {
                $docxRows[] = [
                    'type' => 'group',
                    'text' => (string) ($row['_span_text'] ?? ''),
                    'cells' => array_merge(
                        [(string) ($row['_span_text'] ?? '')],
                        array_fill(0, max(0, count($columns) - 1), ''),
                    ),
                ];

                continue;
            }

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
            'sheet_rows' => array_map(fn (array $row) => $row['cells'], $docxRows),
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

    /**
     * Зөвхөн өөрийн хэлтсийн хүрээнд ажиллах эрх эсэх.
     *
     * «Удирдах (хэлтсийн)», «Удирдах (хамааралтай)» хоёр нь хэлтсийнхээ
     * мөрийг удирдана — албан хаагчаа тэд өөрсдөө хуваарилна.
     */
    private static function unitScoped(?User $user): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        return in_array(ModuleAccess::level($user, 'reports'), ['manage_unit', 'manage_own'], true);
    }

    /**
     * Хэлтэс / агентлаг хуваарилах эрх — зөвхөн бүтэн удирдлага, админ.
     *
     * Хэлтэст хуваарилагдсан удирдлага болон хариуцсан албан хаагч
     * эдгээр баганыг харах/засахгүй.
     */
    private static function canAssignDepartments(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return ModuleAccess::level($user, 'reports') === 'manage';
    }

    /**
     * Баганыг эрхийн түвшингээр шүүнэ.
     *
     * - Томъёо: хүснэгтэд харагдахгүй (хувь сервер дээр бодогдоно).
     * - Бүтэн удирдлага: хэлтэс / агентлаг (албан хаагчийг хэлтсийн нүдэнд сонгоно).
     * - Хэлтсийн / хамааралтай удирдлага: зөвхөн хариуцах албан хаагчаа сонгоно.
     * - Бусад (хуваарилагдсан албан хаагч гэх мэт): хуваарилалтын багана харагдахгүй.
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<array<string, mixed>>
     */
    private static function visibleColumns(array $columns, ?User $user): array
    {
        // Томъёо нь дотоод тохиргоо — хэрэглэгч хүснэгтээс сонгохгүй.
        $hide = ['formula'];

        if (self::unitScoped($user)) {
            array_push($hide, 'department', 'agency');
        } elseif (self::canAssignDepartments($user)) {
            $hide[] = 'assignee';
        } else {
            array_push($hide, 'department', 'agency', 'assignee');
        }

        return array_values(array_filter(
            $columns,
            static fn (array $column) => ! in_array($column['key'] ?? '', $hide, true),
        ));
    }
}
