<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\IndicatorPeriod;
use App\Models\Leave;
use App\Models\Plan;
use App\Models\SectorIndicator;
use App\Models\Task;
use App\Models\TravelAssignment;
use App\Models\WorkGroup;
use App\Support\IndicatorMeasure;
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
        $personalDashboard = ModuleAccess::scopeOwnOnly($user, 'dept_dashboard');

        $leaveQuery = Leave::query();
        $assignQuery = TravelAssignment::query();
        $planQuery = Plan::query();
        $groupQuery = WorkGroup::query()->with('tasks');

        if ($deptId && ! $user->is_admin && ! $personalDashboard) {
            $leaveQuery->where('department_id', $deptId);
            $assignQuery->where('department_id', $deptId);
            $planQuery->where('department_id', $deptId);
            $groupQuery->where('department_id', $deptId);
        }

        if ($personalDashboard) {
            ModuleOwnScope::applyOwnRecords($leaveQuery, $user, 'leaves');
            ModuleOwnScope::applyOwnRecords($assignQuery, $user, 'assignments');
            ModuleOwnScope::applyOwnRecords($planQuery, $user, 'plans');
            ModuleOwnScope::applyOwnRecords($groupQuery, $user, 'work_groups');
        } else {
            ModuleOwnScope::apply($leaveQuery, $user, 'leaves');
            ModuleOwnScope::apply($assignQuery, $user, 'assignments');
            ModuleOwnScope::apply($planQuery, $user, 'plans');
            ModuleOwnScope::apply($groupQuery, $user, 'work_groups');
        }

        // Самбар: зөвхөн тухайн албан хаагчид хамаатай (хариуцагч/хамтран) үүрэг.
        $taskQuery = Task::query()->with('source:id,key,name');
        ModuleOwnScope::restrictTasksToAssignee($taskQuery, $user);

        $tasks = $taskQuery->orderBy('sort_order')->orderBy('id')->get();

        $recentTasks = $tasks
            ->sortBy([
                fn (Task $t) => $t->progress >= 100 ? 1 : 0,
                fn (Task $t) => -$t->id,
            ])
            ->take(8)
            ->values()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'text' => $t->text,
                'progress' => (int) $t->progress,
                'source' => $t->source?->name,
            ]);

        return Inertia::render('Modules/DepartmentDashboard', [
            'department' => $user->department?->only(['id', 'name', 'code']),
            'isAdmin' => (bool) $user->is_admin,
            'stats' => [
                'pending_leaves' => (clone $leaveQuery)->where('status', 'pending')->count(),
                'active_assignments' => (clone $assignQuery)->whereIn('status', ['pending', 'approved'])->count(),
                'active_plans' => (clone $planQuery)->where('status', 'active')->count(),
                'work_groups' => (clone $groupQuery)->count(),
                'open_tasks' => $tasks->where('progress', '<', 100)->count(),
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
            'recentTasks' => $recentTasks,
            'indicatorDashboard' => $this->indicatorDashboard($request, $user),
        ]);
    }

    /**
     * Суурь мэдээллийн үзүүлэлт — нэгжийн жилийн тоог самбар дээр графикаар.
     *
     * @return array<string, mixed>
     */
    private function indicatorDashboard(Request $request, $user): array
    {
        $isManager = $user->is_admin || ModuleAccess::canManage($user, 'legal_acts');

        $units = Department::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        $unitId = $this->indicatorUnit($request, $user, $isManager, $units->pluck('id')->all());

        $periods = IndicatorPeriod::ordered()->get();

        $rows = SectorIndicator::query()
            ->with('values:id,sector_indicator_id,indicator_period_id,slot,value')
            ->when($unitId, fn ($q) => $q->where('department_id', $unitId))
            ->when(! $unitId, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $department = $units->firstWhere('id', $unitId);

        return [
            'unitId' => $unitId,
            'unitName' => $department?->name,
            'canOpen' => ModuleAccess::canView($user, 'legal_acts'),
            'units' => $isManager
                ? $units->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])->values()
                : [],
            'periods' => $periods->map(fn (IndicatorPeriod $p) => [
                'id' => $p->id,
                'label' => $p->label,
            ])->values(),
            'rows' => $rows->map(function (SectorIndicator $row) use ($periods) {
                $stored = [];

                foreach ($row->values as $value) {
                    $stored[$value->indicator_period_id.':'.$value->slot] = $value->value;
                }

                $points = $periods->map(function (IndicatorPeriod $period) use ($stored, $row) {
                    $raw = $stored[$period->id.':year'] ?? $stored[$period->id.':half'] ?? null;

                    return [
                        'label' => $period->label,
                        'value' => IndicatorMeasure::numeric($row->measure_unit, $raw),
                        'display' => IndicatorMeasure::display($row->measure_unit, $raw),
                    ];
                })->values();

                $numbers = $points->pluck('value')->filter(fn ($n) => $n !== null);
                $target = IndicatorMeasure::numeric($row->measure_unit, $row->target);
                $latest = $points->reverse()->first(fn ($point) => $point['value'] !== null);
                $scale = max(1, (float) ($numbers->max() ?? 0), (float) ($target ?? 0));
                $progress = null;

                if ($latest && $target) {
                    $progress = (int) round(min(100, ($latest['value'] / $target) * 100));
                }

                return [
                    'id' => $row->id,
                    'title' => $row->title ?: 'Үзүүлэлт',
                    'measure_unit' => $row->measure_unit,
                    'target' => $target,
                    'target_display' => IndicatorMeasure::display($row->measure_unit, $row->target),
                    'latest_display' => $latest['display'] ?? '',
                    'progress' => $progress,
                    'scale' => $scale,
                    'points' => $points,
                ];
            })->values(),
        ];
    }

    /** Аль нэгжийн суурь үзүүлэлтийг самбарт гаргах вэ. */
    private function indicatorUnit(Request $request, $user, bool $isManager, array $allowed): ?int
    {
        if (! $isManager) {
            $own = $user->department_id ? (int) $user->department_id : null;

            return $own && in_array($own, $allowed, true) ? $own : null;
        }

        $requested = (int) $request->integer('unit');

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        if ($user->department_id && in_array((int) $user->department_id, $allowed, true)) {
            return (int) $user->department_id;
        }

        return $allowed[0] ?? null;
    }
}
