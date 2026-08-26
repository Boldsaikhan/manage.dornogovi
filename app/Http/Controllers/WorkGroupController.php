<?php

namespace App\Http\Controllers;

use App\Models\WorkGroup;
use App\Models\WorkGroupTask;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkGroupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'work_groups'), 403);

        $groups = WorkGroup::query()
            ->with(['tasks', 'department:id,name', 'lead:id,name'])
            ->latest('id')
            ->get()
            ->map(function (WorkGroup $group) {
                $avg = (int) round($group->tasks->avg('progress') ?? 0);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'status' => $group->status,
                    'department' => $group->department?->name,
                    'lead' => $group->lead?->name,
                    'progress' => $avg,
                    'tasks' => $group->tasks->map(fn (WorkGroupTask $t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'owner' => $t->owner,
                        'progress' => $t->progress,
                        'status' => $t->status,
                        'due_on' => optional($t->due_on)->format('Y-m-d'),
                        'note' => $t->note,
                    ]),
                ];
            });

        return Inertia::render('Modules/WorkGroups', [
            'groups' => $groups,
            'canManage' => ModuleAccess::canManage($request->user(), 'work_groups'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'work_groups'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        WorkGroup::create([
            ...$data,
            'department_id' => $request->user()->department_id,
            'lead_user_id' => $request->user()->id,
            'status' => 'active',
        ]);

        return back()->with('success', 'Ажлын хэсэг үүсгэлээ.');
    }

    public function storeTask(Request $request, WorkGroup $workGroup): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'work_groups'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner' => ['nullable', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
        ]);

        $workGroup->tasks()->create([
            ...$data,
            'progress' => 0,
            'status' => 'open',
        ]);

        $recipients = collect([$workGroup->lead_user_id])->filter();
        $notifier = app(\App\Services\Push\EmployeePushNotifier::class);
        $notifier->notifyNamed($data['owner'] ?? null, [
            'title' => 'Ажлын хэсгийн үүрэг',
            'body' => ($workGroup->name ?? 'Ажлын хэсэг').': '.($data['title'] ?? ''),
            'url' => '/modules/work-groups',
            'tag' => 'work-group',
        ]);
        if ($recipients->isNotEmpty()) {
            $notifier->notifyUsers($recipients, [
                'title' => 'Ажлын хэсгийн үүрэг',
                'body' => ($workGroup->name ?? 'Ажлын хэсэг').': '.($data['title'] ?? ''),
                'url' => '/modules/work-groups',
                'tag' => 'work-group',
            ]);
        }

        return back()->with('success', 'Үүрэг нэмлээ.');
    }

    public function updateTask(Request $request, WorkGroupTask $task): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'work_groups'), 403);

        $data = $request->validate([
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'max:24'],
            'note' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'owner' => ['nullable', 'string', 'max:255'],
        ]);

        $task->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', 'Шинэчиллээ.');
    }
}
