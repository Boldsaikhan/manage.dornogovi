<?php

namespace App\Services\Ai\Tools;

use App\Models\Decree;
use App\Models\Department;
use App\Models\Leave;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Support\ModuleAccess;
use Carbon\Carbon;

class SystemTools
{
    public function dashboardBriefing(User $user, array $args = []): array
    {
        $items = [];

        if (ModuleAccess::canView($user, 'tasks')) {
            $open = Task::query()->where('progress', '<', 100)->count();
            $items[] = [
                'tone' => $open > 0 ? 'warn' : 'ok',
                'label' => "Дуусаагүй үүрэг даалгавар: {$open}",
                'route' => 'tasks.index',
            ];
        }

        if (ModuleAccess::canView($user, 'leaves')) {
            $pending = Leave::query()->where('status', 'pending')->count();
            $items[] = [
                'tone' => $pending > 0 ? 'warn' : 'ok',
                'label' => "Хүлээгдэж буй чөлөө: {$pending}",
                'route' => 'leaves.index',
            ];
        }

        if (ModuleAccess::canView($user, 'decrees')) {
            $recent = Decree::query()->where('created_at', '>=', Carbon::now()->subDays(30))->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Сүүлийн 30 хоногийн захирамж/тушаал: {$recent}",
                'route' => 'decrees.index',
            ];
        }

        if (ModuleAccess::canView($user, 'meetings')) {
            $today = Meeting::query()
                ->whereDate('held_at', Carbon::today())
                ->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Өнөөдрийн хурал: {$today}",
                'route' => 'meetings.index',
            ];
        }

        if (ModuleAccess::canView($user, 'plans')) {
            $active = Plan::query()->where('status', 'active')->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Идэвхтэй төлөвлөгөө: {$active}",
                'route' => 'plans.index',
            ];
        }

        return [
            'title' => 'Сүүлийн мэдээллийг нэгтгэлээ',
            'user' => $user->name,
            'items' => $items,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function statistics(User $user, array $args = []): array
    {
        $stats = [];
        $map = [
            'tasks' => fn () => ['label' => 'Үүрэг', 'total' => Task::count(), 'open' => Task::where('progress', '<', 100)->count()],
            'leaves' => fn () => ['label' => 'Чөлөө', 'pending' => Leave::where('status', 'pending')->count(), 'total' => Leave::count()],
            'decrees' => fn () => ['label' => 'Захирамж/тушаал', 'total' => Decree::count(), 'last30' => Decree::where('created_at', '>=', now()->subDays(30))->count()],
            'plans' => fn () => ['label' => 'Төлөвлөгөө', 'active' => Plan::where('status', 'active')->count()],
            'meetings' => fn () => ['label' => 'Хурал', 'total' => Meeting::count()],
        ];

        foreach ($map as $module => $fn) {
            if (ModuleAccess::canView($user, $module)) {
                $stats[$module] = $fn();
            }
        }

        return ['stats' => $stats];
    }

    public function searchEmployees(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = User::query()->with('department:id,name')->orderBy('name')->limit(15);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('position', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get(['id', 'name', 'email', 'position', 'department_id'])->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'position' => $u->position,
                'department' => $u->department?->name,
            ])->all(),
        ];
    }

    public function searchDepartments(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = Department::query()->orderBy('name')->limit(20);
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        return [
            'items' => $query->get(['id', 'name'])->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->all(),
        ];
    }
}
