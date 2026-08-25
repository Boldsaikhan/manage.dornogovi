<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Үүрэг, чиглэлийн биелэлт — өмнө нь тусдаа статик сайт байсныг апп дотор
     * нэгтгэсэн модуль. Өгөгдөл нэг санд тул засвар бүх хэрэглэгчид харагдана.
     */
    public function index(Request $request): Response
    {
        abort_unless(\App\Support\ModuleAccess::canView($request->user(), 'tasks'), 403);

        return Inertia::render('Uureg/Index', [
            'sources' => TaskSource::orderBy('sort_order')->get(['id', 'name', 'period']),
            'tasks' => Task::orderBy('sort_order')->get([
                'id', 'task_source_id', 'text', 'period', 'responsible', 'collaborator',
                'sector', 'department', 'indicator', 'baseline', 'target', 'progress', 'note',
            ]),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['sometimes', 'required', 'string', 'max:2000'],
            'responsible' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collaborator' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'indicator' => ['sometimes', 'nullable', 'string', 'max:255'],
            'baseline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'target' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $task->update($data);

        return back(303);
    }

    /**
     * Нэг хариуцагчийн бүх ажлыг нэг дор тухайн хэлтэст оноох — хэлтсийн
     * зураглалыг мөр мөрөөр бөглөхөөс сэргийлнэ.
     */
    public function assignDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'responsible' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        Task::where('responsible', $data['responsible'])
            ->update(['department' => $data['department'] ?: null]);

        return back(303);
    }
}
