<?php

namespace App\Http\Controllers;

use App\Models\EditUndo;
use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use App\Support\PdfTableWriter;
use App\Support\PersonName;
use App\Support\TaskDocxParser;
use App\Support\XlsxTableWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaskController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'tasks'), 403);

        $user = $request->user();
        $kinds = $this->kindTabs($user);
        $requested = $request->string('kind')->toString();

        if (! $user->is_admin) {
            $allowed = collect($kinds)->pluck('key');

            if ($allowed->isEmpty()) {
                return Inertia::render('Uureg/Index', [
                    'kind' => '',
                    'kinds' => [],
                    'source' => [
                        'id' => null,
                        'key' => '',
                        'name' => 'Үүрэг даалгавар',
                        'layout' => TaskSource::KEY_DIRECTIVE,
                        'is_system' => true,
                        'columns' => (new TaskSource(['key' => TaskSource::KEY_DIRECTIVE, 'layout' => TaskSource::KEY_DIRECTIVE]))->resolvedColumns(),
                    ],
                    'columnChoices' => TaskSource::columnCatalog(),
                    'tasks' => [],
                    'documents' => [],
                    'people' => $this->phoneDirectoryPeople(),
                    ...ModuleAccess::taskPagePermissions($user),
                    'undoCount' => EditUndo::query()->where('user_id', $user->id)->count(),
                ]);
            }

            if ($requested === '' || ! $allowed->contains($requested)) {
                return redirect()->route('tasks.index', ['kind' => $allowed->first()]);
            }
        }

        $source = $this->resolveSource($requested);
        $kind = $source->key;

        $tasksQuery = $source->tasks();
        $this->applyTaskListScope($tasksQuery, $user);
        $tasks = $tasksQuery
            ->get([
                'id', 'task_source_id', 'text', 'measure', 'period', 'responsible',
                'collaborator', 'sector', 'note', 'progress', 'sort_order',
            ])
            ->map(fn (Task $task, int $i) => [
                'id' => $task->id,
                'no' => $i + 1,
                'text' => $task->text,
                'measure' => $task->measure,
                'period' => $task->period,
                'responsible' => PersonName::shortList($task->responsible),
                'collaborator' => PersonName::shortList($task->collaborator),
                'sector' => $task->sector,
                'note' => $task->note,
                'progress' => (int) $task->progress,
                'sort_order' => $task->sort_order,
            ]);

        $documents = $source->documents()
            ->with('uploader:id,name')
            ->get()
            ->map(fn (TaskDocument $doc) => [
                'id' => $doc->id,
                'original_name' => $doc->original_name,
                'size' => $doc->size,
                'uploaded_at' => optional($doc->created_at)?->format('Y-m-d H:i'),
                'uploader' => $doc->uploader?->name,
            ]);

        return Inertia::render('Uureg/Index', [
            'kind' => $kind,
            'kinds' => $kinds,
            'source' => [
                'id' => $source->id,
                'key' => $source->key,
                'name' => $source->name,
                'layout' => $source->layout ?: $source->key,
                'is_system' => $source->isSystem(),
                'columns' => $source->resolvedColumns(),
            ],
            'columnChoices' => TaskSource::columnCatalog(),
            'tasks' => $tasks,
            'documents' => $documents,
            'people' => $this->phoneDirectoryPeople(),
            ...ModuleAccess::taskPagePermissions($user),
            'undoCount' => EditUndo::query()->where('user_id', $user->id)->count(),
        ]);
    }

    /**
     * Идэвхтэй табын хүснэгтийг Word / Excel / PDF файлаар татах.
     */
    public function export(
        Request $request,
        DocxTableWriter $docx,
        XlsxTableWriter $xlsx,
        PdfTableWriter $pdf,
    ): HttpResponse {
        abort_unless(ModuleAccess::canView($request->user(), 'tasks'), 403);

        $source = $this->resolveSource($request->string('kind')->toString());
        $kind = $source->key;

        $format = strtolower((string) $request->query('format', 'docx'));
        abort_unless(in_array($format, ['docx', 'xlsx', 'pdf'], true), 404);

        $tasksQuery = $source->tasks();
        $this->applyTaskListScope($tasksQuery, $request->user());
        $tasks = $tasksQuery
            ->get([
                'id', 'text', 'measure', 'period', 'responsible', 'collaborator', 'sector', 'note', 'progress', 'sort_order',
            ])
            ->values();

        $payload = $this->exportTable($source, $tasks);
        $title = $payload['title'];
        $tmp = tempnam(sys_get_temp_dir(), 'task_export_');

        try {
            if ($format === 'docx') {
                $path = $tmp.'.docx';
                $docx->write(
                    $path,
                    $title,
                    $payload['headings'],
                    $payload['widths'],
                    $payload['docx_rows'],
                    $payload['center'],
                    $payload['landscape'],
                );
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $ascii = 'tasks.docx';
            } elseif ($format === 'xlsx') {
                $path = $tmp.'.xlsx';
                $xlsx->write($path, $title, $payload['headings'], $payload['sheet_rows']);
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $ascii = 'tasks.xlsx';
            } else {
                $path = $tmp.'.pdf';
                $pdf->write($path, $title, $payload['headings'], $payload['sheet_rows'], $payload['landscape']);
                $mime = 'application/pdf';
                $ascii = 'tasks.pdf';
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

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return array{
     *     title: string,
     *     headings: array<int, string>,
     *     widths: array<int, int>,
     *     center: array<int, int>,
     *     landscape: bool,
     *     docx_rows: array<int, array{type: string, cells: array<int, string>}>,
     *     sheet_rows: array<int, array<int, string>>
     * }
     */
    private function exportTable(TaskSource $source, $tasks): array
    {
        $title = $source->name !== '' ? $source->name : (
            $source->isPrepLayout()
                ? 'Бэлтгэл ажил хангах төлөвлөгөө'
                : 'Үүрэг чиглэл'
        );

        $columns = $source->resolvedColumns();
        $headings = array_merge(['№'], array_column($columns, 'label'), ['Биелэлтийн хувь']);
        $widthFor = [
            'sector' => 1800,
            'measure' => 3200,
            'text' => 3600,
            'period' => 1200,
            'responsible' => 1600,
            'collaborator' => 1800,
            'note' => 2200,
        ];
        $widths = array_merge(
            [600],
            array_map(fn (array $col) => $widthFor[$col['key']] ?? 1600, $columns),
            [1000],
        );
        $center = [0, count($headings) - 1];
        $sheetRows = [];
        $docxRows = [];

        foreach ($tasks as $i => $task) {
            $cells = [(string) ($i + 1)];
            foreach ($columns as $column) {
                $field = $column['field'];
                $value = $task->{$field} ?? '';
                if (in_array($field, ['responsible', 'collaborator'], true)) {
                    $value = PersonName::shortList($value);
                }
                $cells[] = (string) $value;
            }
            $cells[] = (string) ((int) $task->progress).'%';
            $sheetRows[] = $cells;
            $docxRows[] = ['type' => 'data', 'cells' => $cells];
        }

        return [
            'title' => $title,
            'headings' => $headings,
            'widths' => $widths,
            'center' => $center,
            'landscape' => true,
            'docx_rows' => $docxRows,
            'sheet_rows' => $sheetRows,
        ];
    }

    /**
     * Утасны жагсаалтын нэрсийн сонголт.
     *
     * @return array<int, array{value: string, label: string, hint: string, org: string, category: string}>
     */
    private function phoneDirectoryPeople(): array
    {
        return PhoneDirectoryEntry::peopleOptions();
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), 'tasks'), 403);
        abort_if(ModuleAccess::tasksProgressOnly($request->user()), 403);

        $data = $request->validate([
            'kind' => ['required', $this->kindRule()],
            'text' => ['nullable', 'string', 'max:5000'],
            'measure' => ['nullable', 'string', 'max:5000'],
            'period' => ['nullable', 'string', 'max:255'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'collaborator' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
        ]);

        $source = TaskSource::query()->where('key', $data['kind'])->firstOrFail();
        $next = ((int) $source->tasks()->max('sort_order')) + 1;
        $text = trim((string) ($data['text'] ?? ''));
        $measure = trim((string) ($data['measure'] ?? ''));
        if ($text === '' && $measure !== '') {
            $text = $measure;
        }

        $source->tasks()->create([
            'text' => $text,
            'measure' => $measure !== '' ? $measure : null,
            'period' => $data['period'] ?? null,
            'responsible' => PersonName::shortList($data['responsible'] ?? null) ?: null,
            'collaborator' => PersonName::shortList($data['collaborator'] ?? null) ?: null,
            'sector' => $data['sector'] ?? null,
            'sort_order' => $next,
            'progress' => 0,
        ]);

        $snippet = mb_substr($text !== '' ? $text : $measure, 0, 80);
        app(\App\Services\Push\EmployeePushNotifier::class)->notifyNamed(
            [$data['responsible'] ?? null, $data['collaborator'] ?? null],
            [
                'title' => 'Шинэ үүрэг даалгавар',
                'body' => $snippet !== '' ? $snippet : 'Танд холбоотой үүрэг нэмэгдлээ.',
                'url' => '/uureg',
                'tag' => 'task',
            ],
        );

        return back(303)->with('success', 'Мөр нэмлээ.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), 'tasks'), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), 'tasks', $task), 403);

        $data = $request->validate([
            'text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'measure' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'period' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsible' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collaborator' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'progress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Нэрийг «Ц.Мөнхбат» хэлбэрт оруулж хадгална.
        foreach (['responsible', 'collaborator'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = PersonName::shortList($data[$field]) ?: null;
            }
        }

        $data = $this->filterTaskUpdateFields($request->user(), $data);

        if ($data === []) {
            abort(403);
        }

        $this->saveTaskWithUndo($request, $task, $data);

        if (array_key_exists('responsible', $data) || array_key_exists('collaborator', $data)) {
            app(\App\Services\Push\EmployeePushNotifier::class)->notifyNamed(
                [
                    $data['responsible'] ?? $task->responsible,
                    $data['collaborator'] ?? $task->collaborator,
                ],
                [
                    'title' => 'Үүрэг шинэчлэгдлээ',
                    'body' => mb_substr(trim((string) $task->text), 0, 80) ?: 'Танд холбоотой үүрэг шинэчлэгдлээ.',
                    'url' => '/uureg',
                    'tag' => 'task-'.$task->id,
                ],
            );
        }

        return back(303);
    }

    /**
     * Олон үүрэг дээр ижил талбаруудыг нэг дор шинэчилнэ.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), 'tasks'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:tasks,id'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'fields.period' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.responsible' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.collaborator' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.sector' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'fields.progress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $fields = $data['fields'];

        foreach (['responsible', 'collaborator'] as $field) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = PersonName::shortList($fields[$field]) ?: null;
            }
        }

        $fields = $this->filterTaskUpdateFields($request->user(), $fields);

        if ($fields === []) {
            abort(403);
        }

        $count = 0;
        Task::query()
            ->whereIn('id', $data['ids'])
            ->get()
            ->each(function (Task $task) use ($request, $fields, &$count) {
                if (! ModuleOwnScope::allows($request->user(), 'tasks', $task)) {
                    return;
                }

                $this->saveTaskWithUndo($request, $task, $fields);
                $count++;
            });

        return back(303)->with('success', "{$count} мөрөнд мэдээлэл орууллаа.");
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), 'tasks'), 403);
        abort_if(ModuleAccess::tasksProgressOnly($request->user()), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), 'tasks', $task), 403);

        EditUndo::recordDelete(
            $request->user(),
            $task,
            $task->only([
                'task_source_id', 'text', 'period', 'responsible', 'collaborator',
                'sector', 'department', 'indicator', 'baseline', 'target',
                'progress', 'note', 'sort_order',
            ]),
            'Үүрэг даалгавар',
            'Мөр устгах',
        );

        $task->delete();

        return back(303)->with('success', 'Мөр устгалаа.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveTaskWithUndo(Request $request, Task $task, array $data): void
    {
        $task->fill($data);
        $dirty = $task->getDirty();

        if (! $dirty) {
            return;
        }

        $original = [];
        foreach (array_keys($dirty) as $field) {
            $original[$field] = $task->getOriginal($field);
        }

        EditUndo::record(
            $request->user(),
            $task,
            $original,
            'Үүрэг даалгавар',
            $this->taskUndoSummary($dirty),
        );

        $task->save();
    }

    /**
     * @param  array<string, mixed>  $dirty
     */
    private function taskUndoSummary(array $dirty): string
    {
        $labels = [
            'text' => 'Үүрэг чиглэл',
            'responsible' => 'Хариуцах эзэн',
            'collaborator' => 'Хяналт',
            'note' => 'Хэрэгжилт',
            'progress' => 'Биелэлтийн хувь',
            'period' => 'Хугацаа',
            'sector' => 'Ажлын чиглэл',
        ];

        $first = array_key_first($dirty);
        $name = $labels[$first] ?? (string) $first;
        $count = count($dirty);

        return $count > 1 ? $name.' болон бусад '.($count - 1) : $name;
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'kind' => ['required', $this->kindRule()],
            'file' => [
                'required',
                'file',
                'max:20480',
                'extensions:doc,docx',
            ],
        ]);

        $this->storeUploadedDocument($request, $data['kind']);

        return back(303)->with('success', 'Word файл хадгаллаа. «Урьдчилан харах» дарж шалгана.');
    }

    /**
     * Word файлаас уншсан мөрүүдийг урьдчилан харуулна (хадгалаагүй).
     */
    public function previewDocument(Request $request): JsonResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'kind' => ['required_without:document_id', $this->kindRule()],
            'file' => [
                'required_without:document_id',
                'file',
                'max:20480',
                'extensions:doc,docx',
            ],
            'document_id' => ['required_without:file', 'integer', 'exists:task_documents,id'],
        ]);

        if ($request->hasFile('file')) {
            $document = $this->storeUploadedDocument($request, $data['kind']);
            $kind = $data['kind'];
        } else {
            $document = TaskDocument::query()->with('source')->findOrFail($data['document_id']);
            $kind = $document->source->key;
        }

        $rows = $this->parseDocumentRows($document, $kind);

        return response()->json([
            'document_id' => $document->id,
            'original_name' => $document->original_name,
            'kind' => $kind,
            'layout' => $document->source?->layout ?: $kind,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    }

    public function importDocument(Request $request, TaskDocument $document): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $imported = $this->importRows($document, $document->source->key, $request->boolean('replace'));

        if ($imported === 0) {
            return back(303)->withErrors([
                'file' => 'Файлаас хүснэгт олдсонгүй. Word дээр .docx хэлбэрээр хадгалж дахин оруулна уу.',
            ]);
        }

        return back(303)->with('success', "{$imported} мөрийг хүснэгтэд уншлаа.");
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function parseDocumentRows(TaskDocument $document, string $kind): array
    {
        if (! Storage::disk('local')->exists($document->path)) {
            return [];
        }

        $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));

        if ($extension !== 'docx') {
            return [];
        }

        try {
            return app(TaskDocxParser::class)->parse(
                Storage::disk('local')->path($document->path),
                $document->source?->layout ?: $kind
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function storeUploadedDocument(Request $request, string $kind): TaskDocument
    {
        $source = TaskSource::query()->where('key', $kind)->firstOrFail();
        $file = $request->file('file');
        $path = $file->store('task-documents/'.$source->key, 'local');

        return $source->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);
    }

    /**
     * Word файлын хүснэгтийг үүргийн мөр болгож хадгална.
     */
    private function importRows(TaskDocument $document, string $kind, bool $replace): int
    {
        $rows = $this->parseDocumentRows($document, $kind);

        if (! $rows) {
            return 0;
        }

        $source = $document->source;

        if ($replace) {
            $source->tasks()->delete();
            $next = 0;
        } else {
            $next = (int) $source->tasks()->max('sort_order');
        }

        foreach ($rows as $row) {
            $next++;
            $source->tasks()->create($row + ['sort_order' => $next, 'progress' => 0]);
        }

        return count($rows);
    }

    public function downloadDocument(Request $request, TaskDocument $document): StreamedResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'tasks'), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name
        );
    }

    public function destroyDocument(Request $request, TaskDocument $document): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $document->delete();

        return back(303)->with('success', 'Файл устгалаа.');
    }

    public function storeSource(Request $request): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'copy_from' => ['nullable', $this->kindRule()],
            'columns' => ['nullable', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in(TaskSource::columnKeys())],
        ]);

        $name = trim($data['name']);
        $columns = TaskSource::normalizeColumnKeys($data['columns'] ?? null);

        if ($columns === [] && filled($data['copy_from'] ?? null)) {
            $template = TaskSource::query()->where('key', $data['copy_from'])->firstOrFail();
            $columns = $template->columnKeyList();
            $layout = $template->layout ?: $template->key;
        } elseif ($columns === []) {
            return back()
                ->withErrors(['columns' => 'Хүснэгтийн толгойгоос дор хаяж нэг талбар сонгоно уу.'])
                ->withInput();
        } else {
            $layout = TaskSource::layoutForColumns($columns);
        }

        if (TaskSource::query()->where('name', $name)->exists()) {
            return back()->with('warning', 'Ийм нэртэй хэсэг аль хэдийн байна.');
        }

        $source = TaskSource::create([
            'key' => TaskSource::keyFor($name),
            'name' => $name,
            'layout' => $layout,
            'columns' => $columns,
            'sort_order' => (int) TaskSource::query()->max('sort_order') + 1,
        ]);

        return redirect()
            ->route('tasks.index', ['kind' => $source->key])
            ->with('success', sprintf('«%s» хэсэг нэмэгдлээ.', $source->name));
    }

    public function destroySource(Request $request, string $source): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $model = TaskSource::query()->where('key', $source)->firstOrFail();
        abort_if($model->isSystem(), 403, 'Суурь хэсгийг устгах боломжгүй.');

        $name = $model->name;
        $model->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', sprintf('«%s» хэсэг устгагдлаа.', $name));
    }

    private function resolveSource(string $kind): TaskSource
    {
        if ($kind !== '') {
            $source = TaskSource::query()->where('key', $kind)->first();

            if ($source) {
                return $source;
            }
        }

        return TaskSource::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    }

    /**
     * «Хамааралтай» эрх — зөвхөн assignee-тай мөр; «бүгд» — бүх мөр.
     */
    private function applyTaskListScope($query, \App\Models\User $user): void
    {
        ModuleOwnScope::apply($query, $user, 'tasks');
    }

    /**
     * @return list<array{key: string, label: string, layout: string, is_system: bool}>
     */
    private function kindTabs(\App\Models\User $user): array
    {
        $query = TaskSource::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        // Хамааралтай эрх — зөвхөн өөрт хамаатай үүрэгтэй хэсэг.
        if (ModuleAccess::scopeOwnOnly($user, 'tasks')) {
            $patterns = PersonName::matchPatterns($user);

            if ($patterns === []) {
                return [];
            }

            $query->whereHas('tasks', function ($tasks) use ($user): void {
                ModuleOwnScope::restrictTasksToAssignee($tasks, $user);
            });
        }

        return $query
            ->get()
            ->map(fn (TaskSource $source) => [
                'key' => $source->key,
                'label' => $source->name,
                'layout' => $source->layout ?: $source->key,
                'is_system' => $source->isSystem(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterTaskUpdateFields(?\App\Models\User $user, array $data): array
    {
        if (! ModuleAccess::tasksProgressOnly($user)) {
            return $data;
        }

        return array_intersect_key($data, array_flip(['note', 'progress']));
    }

    private function kindRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('task_sources', 'key');
    }
}
