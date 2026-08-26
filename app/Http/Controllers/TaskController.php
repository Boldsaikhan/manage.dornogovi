<?php

namespace App\Http\Controllers;

use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\PdfTableWriter;
use App\Support\PersonName;
use App\Support\TaskDocxParser;
use App\Support\XlsxTableWriter;
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
    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'tasks'), 403);

        $kind = $request->string('kind')->toString();
        if (! in_array($kind, [TaskSource::KEY_DIRECTIVE, TaskSource::KEY_PREP_PLAN], true)) {
            $kind = TaskSource::KEY_DIRECTIVE;
        }

        $source = TaskSource::query()->where('key', $kind)->firstOrFail();

        $tasks = $source->tasks()
            ->get([
                'id', 'task_source_id', 'text', 'period', 'responsible',
                'collaborator', 'sector', 'note', 'progress', 'sort_order',
            ])
            ->map(fn (Task $task, int $i) => [
                'id' => $task->id,
                'no' => $i + 1,
                'text' => $task->text,
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
            'source' => [
                'id' => $source->id,
                'key' => $source->key,
                'name' => $source->name,
            ],
            'tasks' => $tasks,
            'documents' => $documents,
            'people' => $this->phoneDirectoryPeople(),
            'canManage' => ModuleAccess::canManage($request->user(), 'tasks')
                || (bool) $request->user()->is_admin,
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

        $kind = $request->string('kind')->toString();
        if (! in_array($kind, [TaskSource::KEY_DIRECTIVE, TaskSource::KEY_PREP_PLAN], true)) {
            $kind = TaskSource::KEY_DIRECTIVE;
        }

        $format = strtolower((string) $request->query('format', 'docx'));
        abort_unless(in_array($format, ['docx', 'xlsx', 'pdf'], true), 404);

        $source = TaskSource::query()->where('key', $kind)->firstOrFail();
        $tasks = $source->tasks()
            ->get([
                'id', 'text', 'period', 'responsible', 'collaborator', 'sector', 'note', 'progress', 'sort_order',
            ])
            ->values();

        $payload = $this->exportTable($kind, $source->name, $tasks);
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
    private function exportTable(string $kind, string $sourceName, $tasks): array
    {
        $title = $sourceName !== '' ? $sourceName : (
            $kind === TaskSource::KEY_PREP_PLAN
                ? 'Бэлтгэл ажил хангах төлөвлөгөө'
                : 'Үүрэг чиглэл'
        );

        if ($kind === TaskSource::KEY_PREP_PLAN) {
            $headings = [
                '№', 'Ажлын чиглэл', 'Арга хэмжээ', 'Хугацаа',
                'Хариуцах эзэн', 'Хамтран хэрэгжүүлэх', 'Хэрэгжилт', 'Биелэлтийн хувь',
            ];
            $widths = [600, 1800, 3600, 1200, 1600, 1600, 2200, 1000];
            $center = [0, 7];
            $sheetRows = [];
            $docxRows = [];

            foreach ($tasks as $i => $task) {
                $cells = [
                    (string) ($i + 1),
                    (string) ($task->sector ?? ''),
                    (string) ($task->text ?? ''),
                    (string) ($task->period ?? ''),
                    PersonName::shortList($task->responsible),
                    PersonName::shortList($task->collaborator),
                    (string) ($task->note ?? ''),
                    (string) ((int) $task->progress).'%',
                ];
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

        $headings = [
            '№', 'Үүрэг чиглэл', 'Хариуцах эзэн',
            'Хяналт тавих албан тушаалтан', 'Хэрэгжилт', 'Биелэлтийн хувь',
        ];
        $widths = [600, 4800, 1600, 1800, 2400, 1000];
        $center = [0, 5];
        $sheetRows = [];
        $docxRows = [];

        foreach ($tasks as $i => $task) {
            $cells = [
                (string) ($i + 1),
                (string) ($task->text ?? ''),
                PersonName::shortList($task->responsible),
                PersonName::shortList($task->collaborator),
                (string) ($task->note ?? ''),
                (string) ((int) $task->progress).'%',
            ];
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
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'kind' => ['required', Rule::in([TaskSource::KEY_DIRECTIVE, TaskSource::KEY_PREP_PLAN])],
            'text' => ['nullable', 'string', 'max:5000'],
            'period' => ['nullable', 'string', 'max:255'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'collaborator' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
        ]);

        $source = TaskSource::query()->where('key', $data['kind'])->firstOrFail();
        $next = ((int) $source->tasks()->max('sort_order')) + 1;

        $source->tasks()->create([
            'text' => $data['text'] ?? '',
            'period' => $data['period'] ?? null,
            'responsible' => PersonName::shortList($data['responsible'] ?? null) ?: null,
            'collaborator' => PersonName::shortList($data['collaborator'] ?? null) ?: null,
            'sector' => $data['sector'] ?? null,
            'sort_order' => $next,
            'progress' => 0,
        ]);

        return back(303)->with('success', 'Мөр нэмлээ.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'text' => ['sometimes', 'nullable', 'string', 'max:5000'],
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

        $task->update($data);

        return back(303);
    }

    /**
     * Олон үүрэг дээр ижил талбаруудыг нэг дор шинэчилнэ.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

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

        $count = 0;
        Task::query()
            ->whereIn('id', $data['ids'])
            ->get()
            ->each(function (Task $task) use ($fields, &$count) {
                $task->update($fields);
                $count++;
            });

        return back(303)->with('success', "{$count} мөрөнд мэдээлэл орууллаа.");
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $task->delete();

        return back(303)->with('success', 'Мөр устгалаа.');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        abort_unless(
            ModuleAccess::canManage($request->user(), 'tasks') || $request->user()->is_admin,
            403
        );

        $data = $request->validate([
            'kind' => ['required', Rule::in([TaskSource::KEY_DIRECTIVE, TaskSource::KEY_PREP_PLAN])],
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:doc,docx',
            ],
        ]);

        $source = TaskSource::query()->where('key', $data['kind'])->firstOrFail();
        $file = $request->file('file');
        $path = $file->store('task-documents/'.$source->key, 'local');

        $document = $source->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        // Файлын хүснэгтийг шууд мөр болгож уншина.
        $imported = $this->importRows($document, $source->key, replace: false);

        return back(303)->with('success', $imported > 0
            ? "Word файл хадгалж, {$imported} мөрийг хүснэгтэд оруулав."
            : 'Word файл хадгаллаа. (Хүснэгт олдсонгүй — .docx хэлбэртэй, хүснэгттэй файл байх шаардлагатай.)');
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
     * Word файлын хүснэгтийг үүргийн мөр болгож хадгална.
     */
    private function importRows(TaskDocument $document, string $kind, bool $replace): int
    {
        if (! Storage::disk('local')->exists($document->path)) {
            return 0;
        }

        $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));

        if ($extension !== 'docx') {
            return 0;
        }

        try {
            $rows = app(TaskDocxParser::class)->parse(
                Storage::disk('local')->path($document->path),
                $kind
            );
        } catch (Throwable) {
            return 0;
        }

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
}
