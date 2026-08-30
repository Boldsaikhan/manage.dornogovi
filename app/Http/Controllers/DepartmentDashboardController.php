<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\Plan;
use App\Models\TravelAssignment;
use App\Models\WorkGroup;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'dept_dashboard'), 403);

        $user = $request->user();
        $deptId = $user->department_id;

        $leaveQuery = Leave::query();
        $assignQuery = TravelAssignment::query();
        $planQuery = Plan::query();
        $groupQuery = WorkGroup::query()->with('tasks');

        if ($deptId && ! $user->is_admin) {
            $leaveQuery->where('department_id', $deptId);
            $assignQuery->where('department_id', $deptId);
            $planQuery->where('department_id', $deptId);
            $groupQuery->where('department_id', $deptId);
        }

        ModuleOwnScope::apply($leaveQuery, $user, 'leaves');
        ModuleOwnScope::apply($assignQuery, $user, 'assignments');
        ModuleOwnScope::apply($planQuery, $user, 'plans');
        ModuleOwnScope::apply($groupQuery, $user, 'work_groups');

        return Inertia::render('Modules/DepartmentDashboard', [
            'department' => $user->department?->only(['id', 'name', 'code']),
            'isAdmin' => (bool) $user->is_admin,
            'stats' => [
                'pending_leaves' => (clone $leaveQuery)->where('status', 'pending')->count(),
                'active_assignments' => (clone $assignQuery)->whereIn('status', ['pending', 'approved'])->count(),
                'active_plans' => (clone $planQuery)->where('status', 'active')->count(),
                'work_groups' => (clone $groupQuery)->count(),
            ],
            'recentLeaves' => $leaveQuery->with('user:id,name')->latest('id')->limit(5)->get(),
            'recentAssignments' => $assignQuery->with('user:id,name')->latest('id')->limit(5)->get(),
            'recentPlans' => $planQuery->latest('id')->limit(5)->get(['id', 'title', 'year', 'period', 'status']),
            'workGroups' => $groupQuery->latest('id')->limit(5)->get()->map(fn (WorkGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'progress' => (int) round($g->tasks->avg('progress') ?? 0),
                'tasks' => $g->tasks->count(),
            ]),
        ]);
    }
}
