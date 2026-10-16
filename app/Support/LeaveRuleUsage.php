<?php

namespace App\Support;

use App\Models\Leave;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Журмын заалт тус бүрийн ашиглалт.
 *
 * Заалт бүр «Жилд / Улиралд / Сард / Тохиолдол бүрт» хэдэн хоног гэж
 * тодорхойлогддог. Албан хаагч тухайн хугацааны дотор лимитээ дүүргэсэн
 * бол тэр заалтыг дахин сонгох боломжгүй.
 */
class LeaveRuleUsage
{
    /**
     * Тухайн мөрийн хувьд сонгох боломжгүй заалтуудын код.
     *
     * @param  Collection<int, Leave>  $all  Бүх бүртгэл (нэг удаа уншсан)
     * @return list<string>
     */
    public static function blockedCodes(Leave $leave, Collection $all): array
    {
        $person = trim((string) $leave->person_name);

        if ($person === '') {
            return [];
        }

        $days = max(0, (int) $leave->days);
        $blocked = [];

        foreach (LeaveLimits::all() as $rule) {
            if ($rule['code'] === '' || (int) $rule['days'] <= 0) {
                // Хоног заагаагүй заалт — хязгаарлахгүй.
                continue;
            }

            if (self::exceeds($rule, $leave, $all, $person, $days)) {
                $blocked[] = $rule['code'];
            }
        }

        return $blocked;
    }

    /**
     * Заалт тус бүрээр тухайн хугацаанд авсан хоног.
     *
     * «Тохиолдол бүрт» заалтад хуримтлал байхгүй тул 0 буцаана.
     *
     * @param  Collection<int, Leave>  $all
     * @return array<string, int>
     */
    public static function usageFor(Leave $leave, Collection $all): array
    {
        $person = trim((string) $leave->person_name);

        if ($person === '') {
            return [];
        }

        $out = [];

        foreach (LeaveLimits::all() as $rule) {
            if ($rule['code'] === '') {
                continue;
            }

            $out[$rule['code']] = $rule['period'] === 'once'
                ? 0
                : self::usedDays($rule, $leave, $all, $person);
        }

        return $out;
    }

    /**
     * Энэ мөрийг энэ заалтаар олговол лимит хэтрэх үү.
     *
     * @param  array{code: string, period: string, days: int}  $rule
     * @param  Collection<int, Leave>  $all
     */
    public static function exceeds(
        array $rule,
        Leave $leave,
        Collection $all,
        string $person,
        int $days,
    ): bool {
        $limit = (int) $rule['days'];

        if ($limit <= 0) {
            return false;
        }

        // «Тохиолдол бүрт» — зөвхөн энэ мөрийн хоног хангалттай.
        if ($rule['period'] === 'once') {
            return $days > $limit;
        }

        $used = self::usedDays($rule, $leave, $all, $person);

        return $used + $days > $limit;
    }

    /**
     * Тухайн хугацааны дотор энэ заалтаар авсан хоног (энэ мөрийг оруулахгүй).
     *
     * @param  array{code: string, period: string, days: int}  $rule
     * @param  Collection<int, Leave>  $all
     */
    public static function usedDays(array $rule, Leave $leave, Collection $all, string $person): int
    {
        $window = self::window($rule['period'], $leave->start_date);

        if ($window === null) {
            return 0;
        }

        [$from, $to] = $window;

        return (int) $all
            ->filter(function (Leave $row) use ($leave, $person, $rule, $from, $to) {
                if ($row->id === $leave->id) {
                    return false;
                }

                if (trim((string) $row->person_name) !== $person) {
                    return false;
                }

                if (trim((string) $row->rule_code) !== $rule['code']) {
                    return false;
                }

                if (! $row->start_date) {
                    return false;
                }

                $start = Carbon::parse($row->start_date);

                return $start->betweenIncluded($from, $to);
            })
            ->sum(fn (Leave $row) => max(0, (int) $row->days));
    }

    /**
     * Давтамжийн эхлэх, дуусах огноо.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private static function window(string $period, mixed $date): ?array
    {
        $start = $date ? Carbon::parse($date) : Carbon::now();

        return match ($period) {
            'year' => [$start->copy()->startOfYear(), $start->copy()->endOfYear()],
            'quarter' => [$start->copy()->startOfQuarter(), $start->copy()->endOfQuarter()],
            'month' => [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()],
            default => null,
        };
    }
}
