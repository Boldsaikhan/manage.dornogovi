<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PhoneDirectoryEntry;
use App\Models\RegulationCategory;
use App\Support\AssignmentSheet;
use App\Support\AssignmentRegisterImporter;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\PdfTableWriter;
use App\Support\XlsxTableWriter;
use App\Support\TabularFileReader;
use App\Support\ModuleOwnScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $module = $this->moduleFromRequest($request);
        $config = $this->configFor($module);
        $this->authorizeModule($request, $module);

        $modelClass = $config['model'];
        /** @var Model $modelClass */
        $query = $modelClass::query()->latest('id');

        if (method_exists($modelClass, 'user')) {
            // Албан тушаал нь хүснэгтийн баганад хэрэгтэй.
            $query->with('user:id,name,position');
        }

        // Хамрах хүрээгээр (агентлаг/сумд/байгууллага) тусад нь бүртгэх — 'all' үед бүгд.
        $scopes = $config['scopes'] ?? [];
        $scopeColumn = $config['scope_column'] ?? 'scope';
        $hideAll = (bool) ($config['hide_all_scope'] ?? false);
        $defaultScope = (string) ($config['default_scope'] ?? ($scopes ? array_key_first($scopes) : 'all'));
        $activeScope = (string) $request->query('scope', $hideAll ? $defaultScope : 'all');

        if (! $scopes || (! $hideAll && $activeScope === 'all')) {
            $activeScope = $hideAll ? $defaultScope : 'all';
        } elseif (! array_key_exists($activeScope, $scopes)) {
            $activeScope = $hideAll ? $defaultScope : 'all';
        }

        if ($scopes && $activeScope !== 'all') {
            $query->where($scopeColumn, $activeScope);
        }

        ModuleOwnScope::apply($query, $request->user(), $module);

        // Таб бүрт өөр багана/талбар
        if ($activeScope !== 'all' && ! empty($config['scope_views'][$activeScope])) {
            $view = $config['scope_views'][$activeScope];
            $config['columns'] = $view['columns'] ?? $config['columns'];
            $config['fields'] = $view['fields'] ?? $config['fields'];
        }

        $scopeTabs = [];
        $counts = collect();
        if ($scopes) {
            $counts = $modelClass::query()
                ->selectRaw("{$scopeColumn} as scope_key, count(*) as aggregate")
                ->groupBy($scopeColumn)
                ->pluck('aggregate', 'scope_key');

            if (! $hideAll) {
                $scopeTabs[] = ['value' => 'all', 'label' => 'Нийт', 'count' => (int) $counts->sum()];
            }
            foreach ($scopes as $value => $label) {
                $scopeTabs[] = ['value' => $value, 'label' => $label, 'count' => (int) ($counts[$value] ?? 0)];
            }
        }

        /*
         * Д/д нь ихээсээ бага руу дугаарлагдана: хамгийн шинэ мөр хамгийн том
         * дугаартай. Тиймээс жагсаалтад ороогүй мөрийг ч тоолж эхлэл дугаарыг
         * гаргана.
         */
        $totalInScope = (clone $query)->toBase()->getCountForPagination();

        $rows = $query->limit(1000)->get()->map(fn (Model $row) => $this->serialize($row, $config, $module));

        $canManageScopes = $module === 'regulations' && ModuleAccess::canManage($request->user(), $module);

        return Inertia::render('Modules/ResourceIndex', [
            'scopeTabs' => $scopeTabs,
            'activeScope' => $activeScope,
            'scopeField' => $scopes ? $scopeColumn : null,
            'scopeCategories' => $canManageScopes
                ? RegulationCategory::manageList($counts->all())
                : [],
            'canManageScopes' => $canManageScopes,
            'module' => $module,
            'title' => $config['title'],
            'description' => $config['description'] ?? '',
            'columns' => $config['columns'],
            'rowNumberLabel' => $config['row_number'] ?? null,
            'rowNumberStart' => $totalInScope,
            'canImportFile' => ($config['file_import'] ?? false)
                && ModuleAccess::canEdit($request->user(), $module),
            'canExportFile' => (bool) ($config['file_export'] ?? false),
            'hasAuditLog' => (bool) ($config['audit_log'] ?? false),
            // Хүснэгтийн нүдэн дээр шууд солих боломжтой талбарууд.
            'inlineFields' => collect($config['inline_fields'] ?? [])
                ->mapWithKeys(fn (string $name) => [
                    $name => $this->inlineOptions($config, $name),
                ])
                ->all(),
            'exportUrl' => ($config['file_export'] ?? false) ? route('modules.export', $module) : null,
            'fields' => $config['fields'],
            'directory' => $this->directoryFor($config),
            'rows' => $rows,
            'rowActions' => $config['row_actions'] ?? [],
            // Тусгай маягттай модуль (жишээ нь томилолтын удирдамж) — A4 хэлбэрээр бөглөнө.
            'formLayout' => $config['form_layout'] ?? null,
            'formMeta' => $this->formMeta($config, $activeScope),
            'canManage' => ModuleAccess::canEdit($request->user(), $module),
            'storeUrl' => route('modules.store', $module),
            'destroyUrlTemplate' => url('/modules/'.$module).'/{id}',
        ]);
    }

    /**
     * A4 маягтаар бөглөх модулийн толгойн мэдээлэл.
     *
     * Идэвхтэй таб (батлах албан тушаалтан) солигдоход «БАТЛАВ» толгой,
     * гарын үсэг зурах хүний нэр хамт өөрчлөгдөнө.
     */
    private function formMeta(array $config, string $activeScope): array
    {
        if (($config['form_layout'] ?? null) !== 'assignment_sheet') {
            return [];
        }

        return [
            'lines' => AssignmentSheet::lines($activeScope),
            'signer' => AssignmentSheet::signerName($activeScope),
            'leaders' => AssignmentSheet::leaders(),
            // Үнэмлэх дээр гарах дугаар — энэ хэсгийн дараагийн Д/д.
            'number' => \App\Models\TravelAssignment::query()
                ->where('approver', $activeScope)
                ->count() + 1,
            // Томилолт хүлээж авах албан хаагчид — утасны жагсаалтаас.
            'people' => collect(PhoneDirectoryEntry::accountPeopleOptions())
                ->map(fn (array $row) => [
                    'name' => $row['value'],
                    'position' => $row['position'],
                    'org' => $row['org'],
                ])
                ->values()
                ->all(),
            'year' => now()->format('Y'),
            'budget_kinds' => AssignmentSheet::BUDGET_KINDS,
        ];
    }

    /**
     * Сонгосон мөрүүдийг Excel / Word / PDF файлаар татна.
     *
     * Мөр сонгоогүй бол идэвхтэй табын бүх бүртгэлийг татна.
     */
    public function export(
        Request $request,
        string $module,
        XlsxTableWriter $xlsx,
        DocxTableWriter $docx,
        PdfTableWriter $pdf,
    ): BinaryFileResponse {
        $config = $this->configFor($module);
        $this->authorizeModule($request, $module);

        abort_unless((bool) ($config['file_export'] ?? false), 404);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        abort_unless(in_array($format, ['xlsx', 'docx', 'pdf'], true), 404);

        $modelClass = $config['model'];
        /** @var Model $modelClass */
        $query = $modelClass::query()->latest('id');

        if (method_exists($modelClass, 'user')) {
            $query->with('user:id,name,position');
        }

        $scopes = $config['scopes'] ?? [];
        $scopeColumn = $config['scope_column'] ?? 'scope';
        $scope = (string) $request->query('scope', 'all');

        if ($scopes && $scope !== 'all' && array_key_exists($scope, $scopes)) {
            $query->where($scopeColumn, $scope);
            $config = $this->applyScopeViewConfig($config, $scope);
        } else {
            $scope = 'all';
        }

        ModuleOwnScope::apply($query, $request->user(), $module);

        // Сонгосон мөр байвал зөвхөн түүнийг татна.
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $columns = $config['columns'];
        $numberLabel = $config['row_number'] ?? null;
        $records = $query->limit(2000)->get();

        // Д/д нь ихээсээ бага руу — хүснэгтэд харагдаж байгаатай ижил.
        $total = $records->count();

        $headings = array_merge(
            $numberLabel ? [$numberLabel] : [],
            array_map(fn (array $col) => (string) $col['label'], $columns),
        );

        $rows = $records->values()->map(function (Model $record, int $index) use ($columns, $config, $module, $numberLabel, $total) {
            $serialized = $this->serialize($record, $config, $module);

            $cells = array_map(
                fn (array $col) => (string) ($serialized[$col['key']] ?? '—'),
                $columns,
            );

            return $numberLabel ? array_merge([(string) ($total - $index)], $cells) : $cells;
        })->all();

        $title = $config['title'].($scope !== 'all' && isset($scopes[$scope]) ? ' — '.$scopes[$scope] : '');
        $tmp = tempnam(sys_get_temp_dir(), 'module_export_');

        if ($format === 'xlsx') {
            $path = $tmp.'.xlsx';
            $xlsx->write($path, $title, $headings, $rows);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'docx') {
            $path = $tmp.'.docx';
            $widths = array_fill(0, count($headings), (int) floor(100 / max(1, count($headings))));

            // Word бичигч нь мөрийг {type, cells} хэлбэрээр хүлээдэг —
            // энгийн жагсаалт өгвөл нүдгүй мөр үүсч, файл нээгдэхгүй.
            $docxRows = array_map(
                fn (array $cells) => ['type' => 'data', 'cells' => $cells],
                $rows,
            );

            $docx->write($path, $title, $headings, $widths, $docxRows, [], landscape: true);
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        } else {
            $path = $tmp.'.pdf';
            $pdf->write($path, $title, $headings, $rows, landscape: true);
            $mime = 'application/pdf';
        }

        @unlink($tmp);

        return response()
            ->download($path, $module.'.'.$format, ['Content-Type' => $mime])
            ->deleteFileAfterSend();
    }

    /**
     * Excel/Word файлыг уншиж, оруулахын өмнө урьдчилан харуулна.
     */
    public function importPreview(
        Request $request,
        string $module,
        TabularFileReader $reader,
        AssignmentRegisterImporter $importer,
    ): JsonResponse {
        $config = $this->configFor($module);

        abort_unless($module === 'assignments', 404);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', TabularFileReader::EXTENSIONS), 'max:20480'],
        ], [], ['file' => 'файл']);

        $file = $request->file('file');
        $rows = $reader->rows($file->getRealPath(), $file->getClientOriginalExtension());

        $analysed = $importer->analyse($rows);
        $entries = $importer->build($analysed['rows'], $analysed['mapping']);

        return response()->json([
            'headers' => $analysed['headers'],
            'mapping' => $analysed['mapping'],
            'fields' => AssignmentRegisterImporter::FIELDS,
            'rows' => array_slice($analysed['rows'], 0, 400),
            'entries' => $entries,
            'total' => count($entries),
        ]);
    }

    /**
     * Урьдчилан харсан мөрүүдийг идэвхтэй табд хадгална.
     */
    public function importStore(
        Request $request,
        string $module,
        AssignmentRegisterImporter $importer,
    ): RedirectResponse {
        $config = $this->configFor($module);

        abort_unless($module === 'assignments', 404);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $data = $request->validate([
            'scope' => ['required', 'string'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*' => ['array'],
        ]);

        $scopes = $config['scopes'] ?? [];

        abort_unless(array_key_exists($data['scope'], $scopes), 422, 'Ийм хэсэг алга.');

        $result = $importer->store($data['scope'], $data['entries']);

        // Импортыг нэг бичлэгээр тэмдэглэнэ — мөр бүрээр биш.
        if ($result['created'] > 0) {
            AuditLog::record(
                modelType: $module,
                modelId: null,
                action: 'imported',
                scope: $data['scope'],
                label: $scopes[$data['scope']] ?? $data['scope'],
                summary: sprintf(
                    '%d мөр нэмэгдэж, %d мөр давхардсан тул алгасав.',
                    $result['created'],
                    $result['skipped'],
                ),
            );
        }

        $message = sprintf('%d мөр нэмэгдлээ.', $result['created']);

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d мөр давхардсан тул алгаслаа.', $result['skipped']);
        }

        // Алдаатай мөрийг нуухгүй — юу нь болоогүйг шууд харуулна.
        if (($result['failed'] ?? 0) > 0) {
            $message .= sprintf(' %d мөр алдаатай: %s', $result['failed'], implode(' | ', $result['errors']));
        }

        return redirect()
            ->route('assignments.index', ['scope' => $data['scope']])
            ->with($result['created'] > 0 ? 'success' : 'warning', $message);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $config = $this->configFor($module);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $config = $this->applyActiveScopeView($request, $config);

        $data = $this->validated($request, $config);
        $data = array_merge($config['defaults'] ?? [], $data);
        $data = $this->applyScopeToData($request, $config, $data);
        ModuleOwnScope::assertCanCreate($request->user(), $module, $data);
        $data = $this->applyCreateHooks($request, $config, $data);
        $data = $this->normalizeDecreeData($config, $data);
        $data = $this->storeUploadedFiles($request, $config, $data);

        if (collect($config['fields'])->contains(fn (array $f) => ($f['name'] ?? '') === 'published_at')
            && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $row = $config['model']::create($data);

        $this->log($module, $row, 'created', $config);

        $this->notifyRelatedEmployees($module, $row, $data);

        return back()->with('success', 'Амжилттай хадгаллаа.');
    }

    /**
     * Өөрчлөлтийн лог үлдээнэ.
     *
     * @param  array<string, mixed>|null  $changes
     */
    private function log(
        string $module,
        Model $row,
        string $action,
        array $config,
        ?string $summary = null,
        ?array $changes = null,
    ): void {
        AuditLog::record(
            modelType: $module,
            modelId: $row->getKey(),
            action: $action,
            scope: (string) ($row->{$config['scope_column'] ?? 'scope'} ?? '') ?: null,
            label: $this->rowLabel($row),
            summary: $summary,
            changes: $changes,
        );
    }

    /** Логт харагдах богино нэр. */
    private function rowLabel(Model $row): string
    {
        foreach (['person_name', 'title', 'name', 'destination'] as $key) {
            $value = trim((string) ($row->{$key} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '#'.$row->getKey();
    }

    /**
     * Өөрчлөгдсөн талбаруудыг хуучин/шинэ утгаар нь тэмдэглэнэ.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: string, to: string}>
     */
    private function changedFields(Model $row, array $data, array $config): array
    {
        $labels = collect($config['fields'] ?? [])->pluck('label', 'name');
        $changes = [];

        foreach ($data as $name => $new) {
            $old = $row->getOriginal($name);

            $oldText = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (string) ($old ?? '');
            $newText = $new instanceof \DateTimeInterface ? $new->format('Y-m-d') : (string) ($new ?? '');

            if ($oldText === $newText) {
                continue;
            }

            $changes[(string) ($labels[$name] ?? $name)] = ['from' => $oldText, 'to' => $newText];
        }

        return $changes;
    }

    /**
     * Тухайн модулийн өөрчлөлтийн түүх.
     */
    public function logs(Request $request, string $module): JsonResponse
    {
        $config = $this->configFor($module);
        $this->authorizeModule($request, $module);

        $scope = (string) $request->query('scope', 'all');

        $rows = AuditLog::query()
            ->with('user:id,name')
            ->where('model_type', $module)
            ->when(
                $scope !== 'all' && array_key_exists($scope, $config['scopes'] ?? []),
                fn ($query) => $query->where('scope', $scope),
            )
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'label' => $log->label,
                'summary' => $log->summary,
                'changes' => $log->changes,
                'scope' => $config['scopes'][$log->scope] ?? $log->scope,
                'user' => $log->user?->name ?? 'Систем',
                'at' => optional($log->created_at)?->format('Y-m-d H:i'),
            ]);

        return response()->json(['rows' => $rows]);
    }

    /**
     * Хүснэгтийн нүдэн дээр нэг талбарыг шууд солино.
     *
     * Бүтэн маягт нээхгүйгээр «Баталсан» гэх мэт нэг талбарыг сольж
     * болно. Зөвхөн тохиргоонд зөвшөөрсөн талбарыг хүлээн авна.
     */
    public function updateField(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->configFor($module);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $row = $config['model']::query()->whereKey($id)->firstOrFail();
        abort_unless(ModuleOwnScope::allows($request->user(), $module, $row), 403);

        $allowed = (array) ($config['inline_fields'] ?? []);
        $name = (string) $request->input('field');

        abort_unless(in_array($name, $allowed, true), 422, 'Энэ талбарыг шууд засах боломжгүй.');

        $field = collect($config['fields'])->firstWhere('name', $name);
        abort_unless($field !== null, 404);

        $rules = ['nullable'];

        if (($field['type'] ?? '') === 'select') {
            $rules[] = Rule::in(array_keys($field['options'] ?? []));
        } else {
            $rules[] = 'string';
        }

        $data = $request->validate(['value' => $rules], [], ['value' => mb_strtolower($field['label'] ?? $name)]);

        $changes = $this->changedFields($row, [$name => $data['value'] ?? null], $config);

        $row->update([$name => $data['value'] ?? null]);

        if ($changes !== []) {
            $this->log($module, $row, 'updated', $config, changes: $changes);
        }

        return back()->with('success', 'Хадгаллаа.');
    }

    /**
     * Засах маягтад дүүргэх түүхий утгуудыг буцаана.
     *
     * Хүснэгтийн жагсаалт хэдэн зуун мөртэй байдаг тул эдгээрийг бүх мөрөнд
     * урьдчилж илгээхгүй — засах товч дарсан үед л татна.
     */
    public function editValues(Request $request, string $module, int $id): JsonResponse
    {
        $config = $this->configFor($module);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $row = $config['model']::query()->whereKey($id)->firstOrFail();
        abort_unless(ModuleOwnScope::allows($request->user(), $module, $row), 403);

        $config = $this->applyActiveScopeView($request, $config);

        $values = [];

        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'text';

            if ($type === 'file') {
                continue;
            }

            $value = $row->{$name} ?? null;

            $values[$name] = match ($type) {
                'checkbox' => (bool) $value,
                'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? ''),
                'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : (string) ($value ?? ''),
                default => (string) ($value ?? ''),
            };
        }

        return response()->json(['values' => $values]);
    }

    /**
     * Бүртгэлтэй мөрийг засна.
     *
     * Шинээр нэмэх маягттай ижил талбаруудыг ашиглана. Файл шинээр
     * оруулаагүй бол хуучин файл хэвээр үлдэнэ.
     */
    public function update(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->configFor($module);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $row = $config['model']::query()->whereKey($id)->firstOrFail();
        abort_unless(ModuleOwnScope::allows($request->user(), $module, $row), 403);

        $config = $this->applyActiveScopeView($request, $config);

        $data = $this->validated($request, $config);
        $data = $this->normalizeDecreeData($config, $data);
        $data = $this->storeUploadedFiles($request, $config, $data);

        $changes = $this->changedFields($row, $data, $config);

        $row->update($data);

        if ($changes !== []) {
            $this->log($module, $row, 'updated', $config, changes: $changes);
        }

        return back()->with('success', 'Хадгаллаа.');
    }

    /**
     * Модулийн бүртгэлээс холбоотой албан хаагчдад push мэдэгдэнэ.
     *
     * @param  array<string, mixed>  $data
     */
    private function notifyRelatedEmployees(string $module, Model $row, array $data): void
    {
        $notifier = app(\App\Services\Push\EmployeePushNotifier::class);

        match ($module) {
            'assignments' => $notifier->notifyUsers(
                array_filter([(int) ($row->user_id ?? 0)]),
                [
                    'title' => 'Томилолт бүртгэгдлээ',
                    'body' => trim(($data['destination'] ?? '').' · '.($data['start_date'] ?? '')),
                    'url' => '/modules/assignments',
                    'tag' => 'assignment',
                ],
            ),
            'meetings' => $notifier->notifyUsers(
                array_filter([(int) ($row->created_by ?? 0)]),
                [
                    'title' => 'Хурлын тэмдэглэл',
                    'body' => (string) ($data['title'] ?? 'Шинэ хурал'),
                    'url' => '/modules/meetings',
                    'tag' => 'meeting',
                ],
            ),
            'plans' => $notifier->notifyUsers(
                array_filter([(int) ($row->created_by ?? 0)]),
                [
                    'title' => 'Төлөвлөгөө бүртгэгдлээ',
                    'body' => (string) ($data['title'] ?? 'Шинэ төлөвлөгөө'),
                    'url' => '/modules/plans',
                    'tag' => 'plan',
                ],
            ),
            default => null,
        };
    }

    public function destroy(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->configFor($module);
        abort_unless(ModuleAccess::canEdit($request->user(), $module), 403);

        $row = $config['model']::query()->whereKey($id)->firstOrFail();
        abort_unless(ModuleOwnScope::allows($request->user(), $module, $row), 403);

        $this->log($module, $row, 'deleted', $config);

        $this->deleteStoredFile($row);
        $row->delete();

        return back()->with('success', 'Устгалаа.');
    }

    /**
     * Оруулсан файлыг браузерт шууд харуулна (PDF) эсвэл татна.
     */
    public function download(Request $request, string $module, int $id): StreamedResponse
    {
        $config = $this->configFor($module);
        $this->authorizeModule($request, $module);

        $row = $config['model']::query()->whereKey($id)->firstOrFail();
        abort_unless(ModuleOwnScope::allows($request->user(), $module, $row), 403);

        $disk = $this->fileDisk($row->file_path ?? null);
        abort_unless($disk, 404);

        $name = (string) ($row->file_name ?: basename((string) $row->file_path));
        $mime = Storage::disk($disk)->mimeType($row->file_path) ?: 'application/octet-stream';
        if ($this->isPdfName($name)) {
            $mime = 'application/pdf';
        }

        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'file';

        return Storage::disk($disk)->response($row->file_path, $name, [
            'Content-Type' => $mime,
            'Content-Disposition' => "inline; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($name),
        ]);
    }

    private function moduleFromRequest(Request $request): string
    {
        $path = trim($request->path(), '/');
        $module = str_contains($path, '/') ? substr($path, strrpos($path, '/') + 1) : $path;

        return $module;
    }

    private function configOrFail(string $module): array
    {
        $config = config("module_resources.{$module}");
        abort_unless(is_array($config), 404);

        return $config;
    }

    private function configFor(string $module): array
    {
        return $this->withDynamicOptions(
            $this->withDynamicScopes($module, $this->configOrFail($module))
        );
    }

    /**
     * Сонгох талбарын утгыг мэдээллийн сангаас бөглөнө.
     *
     * Тохиргооны файлд бичих боломжгүй (утасны жагсаалтаас хамаарсан)
     * сонголтуудыг эндээс нэмнэ.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withDynamicOptions(array $config): array
    {
        foreach ($config['fields'] ?? [] as $index => $field) {
            $source = $field['options_from'] ?? null;

            if ($source === null) {
                continue;
            }

            $config['fields'][$index]['options'] = match ($source) {
                'assignment_leaders' => AssignmentSheet::leaders(),
                default => [],
            };
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withDynamicScopes(string $module, array $config): array
    {
        if (empty($config['dynamic_scopes']) || $module !== 'regulations') {
            return $config;
        }

        $config['scopes'] = RegulationCategory::scopeMap();

        return $config;
    }

    private function authorizeModule(Request $request, string $module): void
    {
        abort_unless(ModuleAccess::canView($request->user(), $module), 403);
    }

    private function validated(Request $request, array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            $rule = [];
            $rule[] = ! empty($field['required']) ? 'required' : 'nullable';

            $rule[] = match ($field['type'] ?? 'text') {
                'number' => 'integer',
                'date' => 'date',
                'datetime' => 'date',
                'checkbox' => 'boolean',
                'file' => 'file',
                'select' => Rule::in(array_keys($field['options'] ?? [])),
                'textarea', 'text', 'directory_org', 'directory_person' => 'string',
                default => 'string',
            };

            if (($field['type'] ?? '') === 'file') {
                $mimes = (string) ($field['mimes'] ?? 'pdf,doc,docx');
                $maxKb = (int) ($field['max_kb'] ?? 20480);
                $rule[] = 'extensions:'.$mimes;
                $rule[] = 'max:'.$maxKb;
            }

            $rules[$name] = $rule;
        }

        $data = $request->validate($rules);

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? '') === 'checkbox') {
                $data[$field['name']] = $request->boolean($field['name']);
            }
            if (($field['type'] ?? '') === 'file') {
                unset($data[$field['name']]);
            }
        }

        return $data;
    }

    /** Тухайн табд тусгайлан тохируулсан багана/талбарыг хэрэглэнэ. */
    private function applyScopeViewConfig(array $config, string $scope): array
    {
        $view = $config['scope_views'][$scope] ?? null;

        if ($view) {
            $config['columns'] = $view['columns'] ?? $config['columns'];
            $config['fields'] = $view['fields'] ?? $config['fields'];
        }

        return $config;
    }

    private function applyActiveScopeView(Request $request, array $config): array
    {
        $scopes = $config['scopes'] ?? [];
        if (! $scopes || empty($config['scope_views'])) {
            return $config;
        }

        $hideAll = (bool) ($config['hide_all_scope'] ?? false);
        $defaultScope = (string) ($config['default_scope'] ?? array_key_first($scopes));
        $scope = (string) ($request->input($config['scope_column'] ?? 'scope')
            ?: $request->query('scope', $hideAll ? $defaultScope : ''));

        if ($scope === '' || $scope === 'all' || ! array_key_exists($scope, $scopes)) {
            return $config;
        }

        $view = $config['scope_views'][$scope] ?? null;
        if ($view) {
            $config['columns'] = $view['columns'] ?? $config['columns'];
            $config['fields'] = $view['fields'] ?? $config['fields'];
        }

        return $config;
    }

    private function applyScopeToData(Request $request, array $config, array $data): array
    {
        $scopes = $config['scopes'] ?? [];
        $scopeColumn = $config['scope_column'] ?? null;
        if (! $scopes || ! $scopeColumn) {
            return $data;
        }

        $scope = (string) ($data[$scopeColumn] ?? $request->input($scopeColumn) ?? $request->query('scope', ''));
        if ($scope === '' || $scope === 'all' || ! array_key_exists($scope, $scopes)) {
            $scope = (string) ($config['default_scope'] ?? array_key_first($scopes));
        }

        $data[$scopeColumn] = $scope;

        return $data;
    }

    /**
     * Бланк / захирамжийн бүртгэлийн заавал талбаруудыг бөглөнө.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeDecreeData(array $config, array $data): array
    {
        if (($config['model'] ?? null) !== \App\Models\Decree::class) {
            return $data;
        }

        if (($data['category'] ?? '') === 'blank') {
            $data['kind'] = $data['kind'] ?? 'blank';
            $blank = trim((string) ($data['blank_number'] ?? ''));
            $data['title'] = filled($data['title'] ?? null)
                ? $data['title']
                : ($blank !== '' ? 'Бланк '.$blank : 'Бланк');
            $data['number'] = $data['number'] ?? null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeUploadedFiles(Request $request, array $config, array $data): array
    {
        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? '') !== 'file') {
                continue;
            }

            $input = $field['name'];
            unset($data[$input]);

            if (! $request->hasFile($input)) {
                continue;
            }

            $file = $request->file($input);
            $folder = (string) ($field['folder'] ?? 'uploads');
            $pathKey = (string) ($field['store_as'] ?? 'file_path');
            $nameKey = (string) ($field['name_as'] ?? 'file_name');

            $data[$pathKey] = $file->store($folder, 'local');
            $data[$nameKey] = $file->getClientOriginalName();
        }

        return $data;
    }

    private function deleteStoredFile(Model $row): void
    {
        $path = $row->file_path ?? null;
        $disk = $this->fileDisk($path);
        if ($disk) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function applyCreateHooks(Request $request, array $config, array $data): array
    {
        $user = $request->user();

        return match ($config['on_create'] ?? null) {
            'attach_user_department' => array_merge($data, [
                'user_id' => $user->id,
                'department_id' => $user->department_id,
            ]),
            'attach_creator' => array_merge($data, [
                'created_by' => $user->id,
            ]),
            'attach_issuer' => array_merge($data, [
                'issued_by' => $user->id,
                'issued_on' => $data['issued_on'] ?? Carbon::today()->toDateString(),
            ]),
            'attach_creator_department' => array_merge($data, [
                'created_by' => $user->id,
                'department_id' => $data['department_id'] ?? $user->department_id,
            ]),
            default => $data,
        };
    }

    /**
     * Утасны жагсаалтад бүртгэлтэй байгууллага, хүмүүсийг сонголт болгож дамжуулна.
     *
     * @return array<int, array<string, mixed>>
     */
    private function directoryFor(array $config): array
    {
        $needed = collect($config['fields'] ?? [])
            ->contains(fn (array $f) => in_array($f['type'] ?? '', ['directory_org', 'directory_person'], true));

        if (! $needed) {
            return [];
        }

        return PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['org_name', 'category', 'person_name', 'position'])
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                // Чөлөөний хамрах хүрээгээр шүүхэд ашиглана.
                'category' => $rows->first()->category ?? 'baiguullaga',
                'people' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'name' => $row->person_name,
                    'position' => $row->position,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function serialize(Model $row, array $config, string $module): array
    {
        $out = ['id' => $row->getKey()];

        foreach ($config['columns'] as $col) {
            $key = $col['key'];
            $out[$key] = match (true) {
                $key === 'file_label', $key === 'file' => $row->file_name ?: (filled($row->file_path ?? null) ? basename($row->file_path) : '—'),
                $key === ($config['scope_column'] ?? 'scope') && ! empty($config['scopes'])
                    => $config['scopes'][$row->{$key}] ?? ($row->{$key} ?? '—'),
                // Сонгох талбарын түлхүүрийг монгол нэрээр нь харуулна.
                ! empty($col['from_options'])
                    => $this->selectOptions($config, $key)[$row->{$key} ?? ''] ?? ($row->{$key} ?? '—'),
                default => $this->serializeValue($row, $key),
            };
        }

        return $this->appendFileMeta($out, $row, $module);
    }

    /**
     * Хүснэгтийн нүдэнд харагдах сонголтууд.
     *
     * Нүд нарийн тул баганад «богино» тэмдэглэгээ хийсэн бол утгыг нь
     * өөрийг нь шошго болгоно (жишээ нь «О.Батжаргал — Засаг дарга»
     * гэхийн оронд зөвхөн «О.Батжаргал»).
     *
     * @return array<string, string>
     */
    private function inlineOptions(array $config, string $name): array
    {
        $options = $this->selectOptions($config, $name);

        $column = collect($config['columns'] ?? [])->firstWhere('key', $name);

        if (! empty($column['inline_short'])) {
            return collect($options)->keys()->mapWithKeys(fn ($key) => [$key => $key])->all();
        }

        return $options;
    }

    /**
     * Тухайн нэртэй талбарын сонголтууд.
     *
     * @return array<string, string>
     */
    private function selectOptions(array $config, string $key): array
    {
        $field = collect($config['fields'] ?? [])->firstWhere('name', $key);

        return $field['options'] ?? [];
    }

    private function serializeValue(Model $row, string $key): string
    {
        return match ($key) {
            // Цаасан бүртгэлээс орсон мөрд системд эрхгүй хүн ч байж болно.
            'user_name' => ($row->person_name ?? null) ?: ($row->user->name ?? '—'),
            'user_position' => ($row->position ?? null) ?: ($row->user->position ?? '—'),
            // Сонгосон бол тэр нэр, эс бөгөөс табын батлах албан тушаалтан.
            'approved_by' => ($row->approved_by ?? null)
                ?: (AssignmentSheet::signerName($row->approver ?? null) ?: '—'),
            // Эхлэх, дуусах огноогоор хоногийг бодно (хоёулаа оруулсан үед).
            'day_count' => $this->dayCount($row),
            'person_label' => $row->person_name ?: ($row->user->name ?? '—'),
            'kind_label' => method_exists($row, 'kindLabel') ? $row->kindLabel() : ($row->kind ?? '—'),
            'for_new_hires' => $row->for_new_hires ? 'Тийм' : 'Үгүй',
            'published_at', 'held_at', 'start_date', 'end_date', 'issued_on', 'due_on' => optional($row->{$key})->format(
                str_contains($key, 'held') ? 'Y-m-d H:i' : 'Y-m-d'
            ) ?? '—',
            default => (string) ($row->{$key} ?? '—'),
        };
    }

    /** «Хэд хоног» — эхлэх ба дуусах өдрийг оролцуулж тоолно. */
    private function dayCount(Model $row): string
    {
        $start = $row->start_date ?? null;
        $end = $row->end_date ?? null;

        if (! $start || ! $end) {
            return '—';
        }

        return (string) ($start->diffInDays($end) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function appendFileMeta(array $out, Model $row, string $module): array
    {
        if (! in_array('file_path', $row->getFillable(), true)) {
            return $out;
        }

        $path = $row->file_path ?? null;
        $name = (string) ($row->file_name ?: ($path ? basename((string) $path) : ''));
        $out['file_name'] = $name !== '' ? $name : null;
        $out['has_file'] = filled($path);
        $out['file_url'] = filled($path) ? route('modules.file', ['module' => $module, 'id' => $row->getKey()]) : null;
        $out['file_is_pdf'] = $this->isPdfName($name);

        return $out;
    }

    private function fileDisk(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function isPdfName(?string $name): bool
    {
        return str_ends_with(strtolower((string) $name), '.pdf');
    }
}
