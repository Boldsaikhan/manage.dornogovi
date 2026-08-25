<?php

namespace App\Http\Controllers;

use App\Models\OrgEmployeePhone;
use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Support\ModuleAccess;
use App\Support\TaskDocxParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'responsible' => $task->responsible,
                'collaborator' => $task->collaborator,
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
     * Утасны жагсаалт + АЗДТГ-н албан хаагчдын нэрсийн сонголт.
     *
     * @return array<int, array{value: string, label: string, hint: string, org: string, category: string}>
     */
    private function phoneDirectoryPeople(): array
    {
        $items = [];

        PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['person_name', 'position', 'org_name', 'category'])
            ->each(function (PhoneDirectoryEntry $row) use (&$items) {
                $name = trim((string) $row->person_name);
                if ($name === '') {
                    return;
                }
                $category = $row->category ?: PhoneDirectoryEntry::guessCategory($row->org_name);
                $items[$name] = [
                    'value' => $name,
                    'label' => $name,
                    'hint' => trim((string) $row->position),
                    'org' => trim((string) $row->org_name),
                    'category' => $category,
                ];
            });

        OrgEmployeePhone::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['last_name', 'first_name', 'position', 'organization', 'unit'])
            ->each(function (OrgEmployeePhone $row) use (&$items) {
                $name = $this->formatEmployeeName($row->last_name, $row->first_name);
                if ($name === '') {
                    return;
                }
                $org = trim(implode(' · ', array_filter([(string) $row->unit, (string) $row->organization])));
                $category = PhoneDirectoryEntry::guessCategory($row->organization ?: $row->unit);
                if (! isset($items[$name])) {
                    $items[$name] = [
                        'value' => $name,
                        'label' => $name,
                        'hint' => trim((string) $row->position),
                        'org' => $org,
                        'category' => $category,
                    ];
                }
            });

        return array_values($items);
    }

    private function formatEmployeeName(?string $lastName, ?string $firstName): string
    {
        $last = trim((string) $lastName);
        $first = trim((string) $firstName);

        if ($last === '' && $first === '') {
            return '';
        }
        if ($last === '') {
            return $first;
        }
        if ($first === '') {
            return $last;
        }

        // Богино овог (жишээ: «Ц») → «Ц.Мөнх-Эрдэнэ»
        if (mb_strlen($last) <= 3) {
            return $last.'.'.$first;
        }

        return $last.' '.$first;
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
            'responsible' => $data['responsible'] ?? null,
            'collaborator' => $data['collaborator'] ?? null,
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

        $task->update($data);

        return back(303);
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
