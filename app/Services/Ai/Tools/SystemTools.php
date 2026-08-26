<?php

namespace App\Services\Ai\Tools;

use App\Models\Decree;
use App\Models\Department;
use App\Models\Leave;
use App\Models\Meeting;
use App\Models\PhoneDirectoryEntry;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Support\ModuleAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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
                'module' => 'tasks',
                'href' => route('tasks.index'),
            ];
        }

        if (ModuleAccess::canView($user, 'leaves')) {
            $pending = Leave::query()->where('status', 'pending')->count();
            $items[] = [
                'tone' => $pending > 0 ? 'warn' : 'ok',
                'label' => "Хүлээгдэж буй чөлөө: {$pending}",
                'route' => 'leaves.index',
                'module' => 'leaves',
                'href' => route('leaves.index'),
            ];
        }

        if (ModuleAccess::canView($user, 'decrees')) {
            $recent = Decree::query()
                ->where(function (Builder $q) {
                    $q->where('issued_on', '>=', Carbon::now()->subDays(30)->toDateString())
                        ->orWhere(function (Builder $inner) {
                            $inner->whereNull('issued_on')
                                ->where('created_at', '>=', Carbon::now()->subDays(30));
                        });
                })
                ->where('kind', '!=', 'blank')
                ->count();
            $total = Decree::query()->where('kind', '!=', 'blank')->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Захирамж/тушаал: нийт {$total}, сүүлийн 30 хоногт {$recent}",
                'route' => 'decrees.index',
                'module' => 'decrees',
                'href' => route('decrees.index'),
            ];
        }

        if (ModuleAccess::canView($user, 'phone_directory')) {
            $phones = PhoneDirectoryEntry::query()->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Утасны жагсаалт: {$phones} бүртгэл",
                'route' => 'phone-directory.index',
                'module' => 'phone_directory',
                'href' => route('phone-directory.index'),
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
                'module' => 'meetings',
                'href' => route('meetings.index'),
            ];
        }

        if (ModuleAccess::canView($user, 'plans')) {
            $active = Plan::query()->where('status', 'active')->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Идэвхтэй төлөвлөгөө: {$active}",
                'route' => 'plans.index',
                'module' => 'plans',
                'href' => route('plans.index'),
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
            'decrees' => fn () => [
                'label' => 'Захирамж/тушаал',
                'total' => Decree::query()->where('kind', '!=', 'blank')->count(),
                'last30' => Decree::query()
                    ->where('kind', '!=', 'blank')
                    ->where(function (Builder $q) {
                        $q->where('issued_on', '>=', now()->subDays(30)->toDateString())
                            ->orWhere(function (Builder $inner) {
                                $inner->whereNull('issued_on')
                                    ->where('created_at', '>=', now()->subDays(30));
                            });
                    })
                    ->count(),
            ],
            'phone_directory' => fn () => [
                'label' => 'Утасны жагсаалт',
                'total' => PhoneDirectoryEntry::query()->count(),
            ],
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

    /**
     * Утасны жагсаалт (phone_directory_entries) — албан хаагч, байгууллага, утас.
     */
    public function searchPhoneDirectory(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $tokens = $this->searchTokens($q);

        $rows = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($tokens === [] ? 25 : 400)
            ->get();

        if ($tokens !== []) {
            $rows = $rows->filter(function (PhoneDirectoryEntry $row) use ($tokens) {
                $hay = mb_strtolower(implode(' ', [
                    (string) $row->person_name,
                    (string) $row->org_name,
                    (string) $row->position,
                    (string) $row->office_phone,
                    (string) $row->mobile_phone,
                ]));

                foreach ($tokens as $token) {
                    $variants = [$token];
                    $len = mb_strlen($token);
                    if ($len >= 4) {
                        $variants[] = mb_substr($token, 0, $len - 1);
                        $variants[] = mb_substr($token, 0, 3);
                    }
                    if ($len >= 5) {
                        $variants[] = mb_substr($token, 0, 4);
                    }

                    $hit = false;
                    foreach (array_unique($variants) as $v) {
                        if (mb_strlen($v) >= 3 && str_contains($hay, mb_strtolower($v))) {
                            $hit = true;
                            break;
                        }
                    }

                    if (! $hit) {
                        return false;
                    }
                }

                return true;
            })->take(25)->values();
        }

        return [
            'items' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                'id' => $row->id,
                'name' => $row->person_name,
                'position' => $row->position,
                'org' => $row->org_name,
                'office_phone' => $row->office_phone,
                'mobile_phone' => $row->mobile_phone,
                'category' => $row->category,
                'route' => 'phone-directory.index',
                'href' => route('phone-directory.index'),
            ])->all(),
            'source' => 'phone_directory',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function searchTokens(string $q): array
    {
        if ($q === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', mb_strtolower($q)) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2) {
                continue;
            }

            // Хоосон үлдэгдэл / туслах үгс
            if (in_array($part, ['аас', 'ол', 'өг', 'ба', 'нь', 'ыг', 'ийг', 'ын', 'ийн'], true)) {
                continue;
            }

            $stem = preg_replace('/(ийн|ыг|ийг|ын|ий|ы)$/u', '', $part) ?? $part;
            if (mb_strlen($stem) >= 3) {
                $tokens[] = $stem;
            } elseif (mb_strlen($part) >= 3) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }
}
