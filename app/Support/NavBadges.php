<?php

namespace App\Support;

use App\Models\Decree;
use App\Models\Leave;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TravelAssignment;
use App\Models\User;
use App\Models\WorkGroup;
use App\Models\WorkGroupTask;
use Illuminate\Database\Eloquent\Builder;

/**
 * Хажуугийн цэсэнд — тухайн албан хаагчид хамаатай нээлттэй/хүлээгдэж буй тоо.
 *
 * @return array<string, int> module_key => count (0-ийг оруулахгүй)
 */
class NavBadges
{
    /**
     * @return array<string, int>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $variants = PersonName::matchPatterns($user);
        $counts = [];

        $add = function (string $key, int $count) use (&$counts, $user): void {
            if ($count > 0 && ModuleAccess::canView($user, $key)) {
                $counts[$key] = $count;
            }
        };

        $add('tasks', self::openTasks($user));
        $add('work_groups', self::openWorkGroupTasks($user, $variants));
        $add('leaves', self::relevantLeaves($user, $variants));
        $add('assignments', self::activeAssignments($user));
        $add('plans', self::draftPlans($user));
        $add('meetings', self::relevantMeetings($user, $variants));
        $add('decrees', self::myBlankSheets($variants));
        $add('dept_dashboard', self::deptActionables($user));

        return $counts;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $variants
     */
    private static function whereNameLike(Builder $query, array $columns, array $variants): Builder
    {
        if (! $variants) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $w) use ($columns, $variants) {
            foreach ($columns as $column) {
                foreach ($variants as $name) {
                    $w->orWhere($column, 'like', '%'.$name.'%');
                }
            }
        });
    }

    private static function openTasks(User $user): int
    {
        $query = Task::query()->where('progress', '<', 100);
        ModuleOwnScope::restrictTasksToAssignee($query, $user);

        return $query->count();
    }

    /**
     * @param  array<int, string>  $variants
     */
    private static function openWorkGroupTasks(User $user, array $variants): int
    {
        $leadGroupIds = WorkGroup::query()
            ->where('lead_user_id', $user->id)
            ->pluck('id');

        if ($leadGroupIds->isEmpty() && ! $variants) {
            return 0;
        }

        return WorkGroupTask::query()
            ->where(function (Builder $q) {
                $q->where('status', 'open')
                    ->orWhere(function (Builder $p) {
                        $p->where('progress', '<', 100)
                            ->where(function (Builder $s) {
                                $s->whereNull('status')->orWhere('status', '!=', 'done');
                            });
                    });
            })
            ->where(function (Builder $q) use ($leadGroupIds, $variants) {
                if ($leadGroupIds->isNotEmpty()) {
                    $q->whereIn('work_group_id', $leadGroupIds);
                }

                foreach ($variants as $name) {
                    $q->orWhere('owner', 'like', '%'.$name.'%');
                }
            })
            ->count();
    }

    /**
     * @param  array<int, string>  $variants
     */
    private static function relevantLeaves(User $user, array $variants): int
    {
        // Батлах эрхтэй / хэлтсийн дарга — хүлээгдэж буй чөлөө
        if (ModuleAccess::canManage($user, 'leaves') || $user->is_department_head || $user->is_admin) {
            $q = Leave::query()->where('status', 'pending');

            if (! $user->is_admin && $user->department_id) {
                $q->where('department_id', $user->department_id);
            }

            return $q->count();
        }

        // Энгийн ажилтан — өөрийн хүлээгдэж буй / удахгүй эхлэх чөлөө
        return Leave::query()
            ->where(function (Builder $q) {
                $q->where('status', 'pending')
                    ->orWhere(function (Builder $a) {
                        $a->where('status', 'approved')
                            ->whereDate('end_date', '>=', now()->toDateString());
                    });
            })
            ->where(function (Builder $q) use ($user, $variants) {
                $q->where('user_id', $user->id);
                if ($variants) {
                    $q->orWhere(function (Builder $n) use ($variants) {
                        self::whereNameLike($n, ['person_name'], $variants);
                    });
                }
            })
            ->count();
    }

    private static function activeAssignments(User $user): int
    {
        return TravelAssignment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('end_date', '>=', now()->toDateString())
            ->count();
    }

    private static function draftPlans(User $user): int
    {
        $q = Plan::query()->where('status', 'draft');

        if ($user->is_admin) {
            return $q->count();
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->where('created_by', $user->id);
            if ($user->department_id && ($user->is_department_head || ModuleAccess::canManage($user, 'plans'))) {
                $w->orWhere('department_id', $user->department_id);
            }
        })->count();
    }

    /**
     * @param  array<int, string>  $variants
     */
    private static function relevantMeetings(User $user, array $variants): int
    {
        $drafts = Meeting::query()
            ->where('created_by', $user->id)
            ->where('status', 'draft')
            ->count();

        $today = Meeting::query()
            ->whereDate('held_at', now()->toDateString())
            ->where('status', '!=', 'draft')
            ->limit(40)
            ->get()
            ->filter(fn (Meeting $m) => self::attendeesMatch($m->attendees, $variants) || $m->created_by === $user->id)
            ->count();

        return $drafts + $today;
    }

    /**
     * @param  mixed  $attendees
     * @param  array<int, string>  $variants
     */
    private static function attendeesMatch(mixed $attendees, array $variants): bool
    {
        if (! $variants || $attendees === null) {
            return false;
        }

        $haystack = is_array($attendees)
            ? implode(' ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $attendees))
            : (string) $attendees;

        foreach ($variants as $name) {
            if ($name !== '' && mb_stripos($haystack, $name) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $variants
     */
    private static function myBlankSheets(array $variants): int
    {
        return self::whereNameLike(
            Decree::query()->where('category', 'blank'),
            ['person_name'],
            $variants,
        )->count();
    }

    private static function deptActionables(User $user): int
    {
        $leaves = Leave::query()->where('status', 'pending');
        $assignments = TravelAssignment::query()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('end_date', '>=', now()->toDateString());

        if (ModuleAccess::scopeOwnOnly($user, 'dept_dashboard')) {
            ModuleOwnScope::applyOwnRecords($leaves, $user, 'leaves');
            ModuleOwnScope::applyOwnRecords($assignments, $user, 'assignments');
        } elseif (! $user->is_admin && $user->department_id) {
            $leaves->where('department_id', $user->department_id);
            $assignments->where('department_id', $user->department_id);
        } elseif (! $user->is_admin) {
            // Хэлтэсгүй бол зөвхөн өөрийнх
            $leaves->where('user_id', $user->id);
            $assignments->where('user_id', $user->id);
        }

        return $leaves->count() + $assignments->count();
    }
}
