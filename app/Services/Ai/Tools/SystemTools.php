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
use App\Services\Ai\AiSettings;
use App\Support\ModuleOwnScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SystemTools
{
    public function __construct(private AiSettings $settings) {}

    public function dashboardBriefing(User $user, array $args = []): array
    {
        $items = [];

        if ($this->settings->userMayRead($user, 'tasks')) {
            $openQuery = Task::query()->where('progress', '<', 100);
            ModuleOwnScope::apply($openQuery, $user, 'tasks');
            $open = $openQuery->count();
            $items[] = [
                'tone' => $open > 0 ? 'warn' : 'ok',
                'label' => "Дуусаагүй үүрэг даалгавар: {$open}",
                'route' => 'tasks.index',
                'module' => 'tasks',
                'href' => route('tasks.index'),
            ];
        }

        if ($this->settings->userMayRead($user, 'leaves')) {
            $pendingQuery = Leave::query()->where('status', 'pending');
            ModuleOwnScope::apply($pendingQuery, $user, 'leaves');
            $pending = $pendingQuery->count();
            $items[] = [
                'tone' => $pending > 0 ? 'warn' : 'ok',
                'label' => "Хүлээгдэж буй чөлөө: {$pending}",
                'route' => 'leaves.index',
                'module' => 'leaves',
                'href' => route('leaves.index'),
            ];
        }

        if ($this->settings->userMayRead($user, 'decrees')) {
            $recentQuery = Decree::query()
                ->where(function (Builder $q) {
                    $q->where('issued_on', '>=', Carbon::now()->subDays(30)->toDateString())
                        ->orWhere(function (Builder $inner) {
                            $inner->whereNull('issued_on')
                                ->where('created_at', '>=', Carbon::now()->subDays(30));
                        });
                })
                ->where('kind', '!=', 'blank');
            ModuleOwnScope::apply($recentQuery, $user, 'decrees');
            $recent = $recentQuery->count();

            $totalQuery = Decree::query()->where('kind', '!=', 'blank');
            ModuleOwnScope::apply($totalQuery, $user, 'decrees');
            $total = $totalQuery->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Захирамж/тушаал: нийт {$total}, сүүлийн 30 хоногт {$recent}",
                'route' => 'decrees.index',
                'module' => 'decrees',
                'href' => route('decrees.index'),
            ];
        }

        if ($this->settings->userMayRead($user, 'phone_directory')) {
            $phones = PhoneDirectoryEntry::query()->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Утасны жагсаалт: {$phones} бүртгэл",
                'route' => 'phone-directory.index',
                'module' => 'phone_directory',
                'href' => route('phone-directory.index'),
            ];
        }

        if ($this->settings->userMayRead($user, 'meetings')) {
            $todayQuery = Meeting::query()->whereDate('held_at', Carbon::today());
            ModuleOwnScope::apply($todayQuery, $user, 'meetings');
            $today = $todayQuery->count();
            $items[] = [
                'tone' => 'info',
                'label' => "Өнөөдрийн хурал: {$today}",
                'route' => 'meetings.index',
                'module' => 'meetings',
                'href' => route('meetings.index'),
            ];
        }

        if ($this->settings->userMayRead($user, 'plans')) {
            $activeQuery = Plan::query()->where('status', 'active');
            ModuleOwnScope::apply($activeQuery, $user, 'plans');
            $active = $activeQuery->count();
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

        if ($this->settings->userMayRead($user, 'tasks')) {
            $stats['tasks'] = [
                'label' => 'Үүрэг',
                'total' => $this->scopedCount($user, 'tasks', Task::query()),
                'open' => $this->scopedCount($user, 'tasks', Task::query()->where('progress', '<', 100)),
            ];
        }

        if ($this->settings->userMayRead($user, 'leaves')) {
            $stats['leaves'] = [
                'label' => 'Чөлөө',
                'pending' => $this->scopedCount($user, 'leaves', Leave::query()->where('status', 'pending')),
                'total' => $this->scopedCount($user, 'leaves', Leave::query()),
            ];
        }

        if ($this->settings->userMayRead($user, 'decrees')) {
            $last30 = Decree::query()
                ->where('kind', '!=', 'blank')
                ->where(function (Builder $q) {
                    $q->where('issued_on', '>=', now()->subDays(30)->toDateString())
                        ->orWhere(function (Builder $inner) {
                            $inner->whereNull('issued_on')
                                ->where('created_at', '>=', now()->subDays(30));
                        });
                });
            $stats['decrees'] = [
                'label' => 'Захирамж/тушаал',
                'total' => $this->scopedCount($user, 'decrees', Decree::query()->where('kind', '!=', 'blank')),
                'last30' => $this->scopedCount($user, 'decrees', $last30),
            ];
        }

        if ($this->settings->userMayRead($user, 'phone_directory')) {
            $stats['phone_directory'] = [
                'label' => 'Утасны жагсаалт',
                'total' => PhoneDirectoryEntry::query()->count(),
            ];
        }

        if ($this->settings->userMayRead($user, 'plans')) {
            $stats['plans'] = [
                'label' => 'Төлөвлөгөө',
                'active' => $this->scopedCount($user, 'plans', Plan::query()->where('status', 'active')),
            ];
        }

        if ($this->settings->userMayRead($user, 'meetings')) {
            $stats['meetings'] = [
                'label' => 'Хурал',
                'total' => $this->scopedCount($user, 'meetings', Meeting::query()),
            ];
        }

        return ['stats' => $stats];
    }

    private function scopedCount(User $user, string $module, Builder $query): int
    {
        ModuleOwnScope::apply($query, $user, $module);

        return $query->count();
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
        $q = $this->stripPhoneQueryNoise(trim((string) ($args['q'] ?? '')));
        $aliasTerms = $this->matchingOrgAliasTerms($q);
        $tokens = $this->tokensOutsideAliases($this->searchTokens($q), $aliasTerms);
        $hasFilter = $aliasTerms !== [] || $tokens !== [];

        $rows = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($hasFilter) {
            $rows = $rows->filter(function (PhoneDirectoryEntry $row) use ($tokens, $aliasTerms) {
                if ($aliasTerms !== [] && ! $this->rowMatchesAliases($row, $aliasTerms)) {
                    return false;
                }

                return $tokens === [] || $this->rowMatchesTokens($row, $tokens);
            })->values();
        }

        $rows = $rows->take($hasFilter ? 80 : 25);

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
            'query' => $q,
        ];
    }

    private function stripPhoneQueryNoise(string $q): string
    {
        $stop = [
            'албан хаагчдын', 'албан хаагчийн', 'албан хаагчдад', 'албан хаагчид', 'албан хаагч',
            'ажилтнуудын', 'ажилтнууд', 'ажилтны', 'ажилтан',
            'хүмүүсийн', 'хүмүүс',
            'гаргаж өг', 'олж өг', 'дугаарыг', 'дугаарууд', 'дугаар',
            'мэдээллийг', 'мэдээлэл',
            'утасны', 'утас',
            'жагсаалтаас', 'жагсаалт', 'жагсаа',
            'гаргаж', 'гарга', 'харуул', 'хайж', 'хай',
            'бүгдийг', 'бүгд', 'бүхийг', 'бүх', 'нийтийг', 'нийт',
        ];
        usort($stop, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $clean = mb_strtolower($q);
        foreach ($stop as $s) {
            $clean = str_replace($s, ' ', $clean);
        }

        return trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
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
        $noise = [
            'аас', 'ол', 'өг', 'ба', 'нь', 'ыг', 'ийг', 'ын', 'ийн',
            'бүх', 'бүгд', 'бүгдийг', 'бүхийг', 'нийт', 'нийтийг',
            'гаргаж', 'гарга', 'харуул', 'хайж', 'хай', 'олж',
            'дугаар', 'дугаарыг', 'дугаарууд',
            'утас', 'утасны',
            'хаагч', 'хаагчид', 'хаагчийн', 'хаагчдын',
            'ажилтан', 'ажилтны', 'ажилтнууд',
            'мэдээлэл', 'мэдээллийг',
            'жагсаалт', 'жагсаалтаас', 'жагсаа',
            'хүн', 'хүмүүс', 'хүмүүсийн',
            'надад', 'над',
        ];

        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2) {
                continue;
            }

            if (in_array($part, $noise, true)) {
                continue;
            }

            $stem = preg_replace('/(ийн|ыг|ийг|ын|ий|ы)$/u', '', $part) ?? $part;
            if (in_array($stem, $noise, true)) {
                continue;
            }

            if (mb_strlen($stem) >= 3) {
                $tokens[] = $stem;
            } elseif (mb_strlen($part) >= 3) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function orgAliasGroups(): array
    {
        return [
            [
                'төрийн сан',
                'төрийн сангийн газар',
                'төрийн сангийн хэлтэс',
                'санхүү, төрийн сан',
                'санхүү төрийн сан',
                'treasury',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function matchingOrgAliasTerms(string $q): array
    {
        $hay = mb_strtolower($q);
        $compact = preg_replace('/\s+/u', '', $hay) ?? $hay;

        foreach ($this->orgAliasGroups() as $group) {
            foreach ($group as $term) {
                $t = mb_strtolower($term);
                $tCompact = preg_replace('/\s+/u', '', $t) ?? $t;
                if (str_contains($hay, $t) || ($tCompact !== '' && str_contains($compact, $tCompact))) {
                    return $group;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $aliasTerms
     * @return array<int, string>
     */
    private function tokensOutsideAliases(array $tokens, array $aliasTerms): array
    {
        if ($aliasTerms === [] || $tokens === []) {
            return $tokens;
        }

        $aliasHay = mb_strtolower(implode(' ', $aliasTerms));

        return array_values(array_filter($tokens, function (string $token) use ($aliasHay) {
            foreach ($this->tokenVariants($token) as $v) {
                if (mb_strlen($v) >= 3 && str_contains($aliasHay, $v)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<int, string>  $aliasTerms
     */
    private function rowMatchesAliases(PhoneDirectoryEntry $row, array $aliasTerms): bool
    {
        $hay = mb_strtolower(implode(' ', [
            (string) $row->org_name,
            (string) $row->position,
            (string) $row->person_name,
        ]));

        foreach ($aliasTerms as $term) {
            $t = mb_strtolower($term);
            if (mb_strlen($t) >= 4 && str_contains($hay, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function rowMatchesTokens(PhoneDirectoryEntry $row, array $tokens): bool
    {
        $hay = mb_strtolower(implode(' ', [
            (string) $row->person_name,
            (string) $row->org_name,
            (string) $row->position,
            (string) $row->office_phone,
            (string) $row->mobile_phone,
        ]));

        foreach ($tokens as $token) {
            $hit = false;
            foreach ($this->tokenVariants($token) as $v) {
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
    }

    /**
     * @return array<int, string>
     */
    private function tokenVariants(string $token): array
    {
        $token = mb_strtolower($token);
        $variants = [$token];
        $len = mb_strlen($token);

        if ($len >= 4) {
            $variants[] = mb_substr($token, 0, $len - 1);
            $variants[] = mb_substr($token, 0, 3);
        }
        if ($len >= 5) {
            $variants[] = mb_substr($token, 0, 4);
        }

        return array_values(array_unique($variants));
    }
}
