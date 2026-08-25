<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Support\ModuleAccess;
use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, callable(User, array): array> */
    private array $tools = [];

    /** @var array<string, string|null> module key required for view, null = any auth */
    private array $permissions = [];

    public function __construct(
        private SystemTools $system,
        private TaskTools $tasks,
        private LeaveTools $leaves,
        private DocumentTools $documents,
    ) {
        $this->register('get_dashboard_briefing', null, fn (User $u, array $a) => $this->system->dashboardBriefing($u, $a));
        $this->register('get_system_statistics', null, fn (User $u, array $a) => $this->system->statistics($u, $a));
        $this->register('search_employees', null, fn (User $u, array $a) => $this->system->searchEmployees($u, $a));
        $this->register('search_departments', null, fn (User $u, array $a) => $this->system->searchDepartments($u, $a));

        $this->register('search_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->search($u, $a));
        $this->register('get_my_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->mine($u, $a));
        $this->register('get_overdue_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->overdue($u, $a));
        $this->register('get_task_report', 'tasks', fn (User $u, array $a) => $this->tasks->report($u, $a));

        $this->register('get_my_leave', 'leaves', fn (User $u, array $a) => $this->leaves->mine($u, $a));
        $this->register('search_leaves', 'leaves', fn (User $u, array $a) => $this->leaves->search($u, $a));
        $this->register('prepare_leave_request', 'leaves', fn (User $u, array $a) => $this->leaves->prepareCreate($u, $a));

        $this->register('search_orders', 'decrees', fn (User $u, array $a) => $this->documents->searchDecrees($u, $a));
        $this->register('search_directives', 'decrees', fn (User $u, array $a) => $this->documents->searchDecrees($u, $a));
        $this->register('search_documents', 'regulations', fn (User $u, array $a) => $this->documents->searchRegulations($u, $a));
        $this->register('search_archive', 'archives', fn (User $u, array $a) => $this->documents->searchArchives($u, $a));
        $this->register('search_contracts', 'contracts', fn (User $u, array $a) => $this->documents->searchContracts($u, $a));
        $this->register('search_plans', 'plans', fn (User $u, array $a) => $this->documents->searchPlans($u, $a));
        $this->register('search_meetings', 'meetings', fn (User $u, array $a) => $this->documents->searchMeetings($u, $a));
        $this->register('search_reports', 'reports', fn (User $u, array $a) => $this->documents->searchReports($u, $a));
        $this->register('get_my_business_trips', 'assignments', fn (User $u, array $a) => $this->documents->myTrips($u, $a));
    }

    public function register(string $name, ?string $module, callable $handler): void
    {
        $this->tools[$name] = $handler;
        $this->permissions[$name] = $module;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return array{ok: bool, denied?: bool, data?: array, error?: string}
     */
    public function run(string $name, User $user, array $args = []): array
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Unknown tool: {$name}");
        }

        $module = $this->permissions[$name] ?? null;
        if ($module && ! ModuleAccess::canView($user, $module)) {
            return [
                'ok' => false,
                'denied' => true,
                'error' => 'Энэ мэдээллийг харах эрх танд байхгүй байна.',
            ];
        }

        return [
            'ok' => true,
            'data' => ($this->tools[$name])($user, $args),
        ];
    }
}
