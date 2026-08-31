<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiSettings;
use App\Support\ModuleAccess;
use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, callable(User, array): array> */
    private array $tools = [];

    /** @var array<string, string|null> module key required for view, null = any auth */
    private array $permissions = [];

    /** @var array<string, string> Хэрэгсэл бүрийн шаардах түвшин: read | write */
    private array $levels = [];

    public function __construct(
        private SystemTools $system,
        private TaskTools $tasks,
        private LeaveTools $leaves,
        private DocumentTools $documents,
        private AiSettings $settings,
    ) {
        $this->register('get_dashboard_briefing', null, fn (User $u, array $a) => $this->system->dashboardBriefing($u, $a));
        $this->register('get_system_statistics', null, fn (User $u, array $a) => $this->system->statistics($u, $a));
        $this->register('search_employees', null, fn (User $u, array $a) => $this->system->searchEmployees($u, $a));
        $this->register('search_departments', null, fn (User $u, array $a) => $this->system->searchDepartments($u, $a));
        $this->register('search_phone_directory', 'phone_directory', fn (User $u, array $a) => $this->system->searchPhoneDirectory($u, $a));

        $this->register('search_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->search($u, $a));
        $this->register('get_my_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->mine($u, $a));
        $this->register('get_overdue_tasks', 'tasks', fn (User $u, array $a) => $this->tasks->overdue($u, $a));
        $this->register('get_task_report', 'tasks', fn (User $u, array $a) => $this->tasks->report($u, $a));

        $this->register('get_my_leave', 'leaves', fn (User $u, array $a) => $this->leaves->mine($u, $a));
        $this->register('search_leaves', 'leaves', fn (User $u, array $a) => $this->leaves->search($u, $a));
        // Бүртгэл үүсгэхэд бэлддэг тул бичих эрх шаардана.
        $this->register('prepare_leave_request', 'leaves', fn (User $u, array $a) => $this->leaves->prepareCreate($u, $a), AiSettings::ACCESS_WRITE);

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

    public function register(
        string $name,
        ?string $module,
        callable $handler,
        string $level = AiSettings::ACCESS_READ,
    ): void {
        $this->tools[$name] = $handler;
        $this->permissions[$name] = $module;
        $this->levels[$name] = $level;
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
        $settingsKey = $module ?: AiSettings::GENERAL_MODULE;
        $level = $this->levels[$name] ?? AiSettings::ACCESS_READ;

        if (! $this->settings->userMayRead($user, $settingsKey)) {
            return [
                'ok' => false,
                'denied' => true,
                'error' => $module && ! ModuleAccess::canView($user, $module)
                    ? 'Энэ мэдээллийг харах эрх танд байхгүй байна.'
                    : 'Энэ цэс рүү хандахыг Manage AI-д зөвшөөрөөгүй байна.',
            ];
        }

        if ($level === AiSettings::ACCESS_WRITE && ! $this->settings->userMayWrite($user, $settingsKey)) {
            return [
                'ok' => false,
                'denied' => true,
                'error' => ! ModuleAccess::canEdit($user, $settingsKey)
                    ? 'Энэ цэст бүртгэл үүсгэх хандах эрх танд байхгүй байна.'
                    : 'Энэ цэст бүртгэл үүсгэх эрхийг Manage AI-д өгөөгүй байна.',
            ];
        }

        return [
            'ok' => true,
            'data' => ($this->tools[$name])($user, $args),
        ];
    }
}
