<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'collaborator', 'sector', 'sort_order',
            ])
            ->map(fn (Task $task, int $i) => [
                'id' => $task->id,
                'no' => $i + 1,
                'text' => $task->text,
                'period' => $task->period,
                'responsible' => $task->responsible,
                'collaborator' => $task->collaborator,
                'sector' => $task->sector,
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
            'canManage' => ModuleAccess::canManage($request->user(), 'tasks')
                || (bool) $request->user()->is_admin,
        ]);
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

        $source->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        return back(303)->with('success', 'Word файл хадгаллаа.');
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
