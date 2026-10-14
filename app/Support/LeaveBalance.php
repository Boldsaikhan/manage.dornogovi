<?php

namespace App\Support;

use App\Models\Leave;
use Illuminate\Support\Carbon;

/**
 * Жилийн цалинтай чөлөөний лимитийг хэрэгжүүлнэ.
 *
 * Журмын 5.4.11: «Жилд ажлын 5 хоногоос илүүгүй» цалинтай чөлөө олгоно.
 * Тиймээс нэг албан хаагчийн нэг жилийн цалинтай чөлөөний нийлбэр
 * лимитээс хэтэрвэл, хэтрүүлсэн мөр нь «Цалингүй» болж, түүнээс хойш
 * төрлийг нь солих боломжгүй болно.
 *
 * Ээлжийн амралтаас авсан чөлөө энэ лимитэд ороогүй.
 */
class LeaveBalance
{
    /**
     * Тухайн хүний тухайн жилийн бүртгэлийг дахин тооцно.
     *
     * Огноогоор нь эрэмбэлж, лимит дүүрэх хүртэлх мөрүүд цалинтай
     * хэвээр, цаашдынх нь цалингүй болно.
     */
    public static function reapply(?string $person, ?int $year): void
    {
        $person = trim((string) $person);

        if ($person === '' || ! $year) {
            return;
        }

        $limit = LeaveLimits::paidDaysPerYear();

        $rows = Leave::query()
            ->where('person_name', $person)
            ->whereYear('start_date', $year)
            ->whereIn('type', ['tsalintai', 'tsalingui'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $used = 0;

        foreach ($rows as $row) {
            $days = max(0, (int) $row->days);

            // Лимитгүй (0) бол бүгд цалинтай хэвээр.
            $exceeded = $limit > 0 && $used + $days > $limit;

            if (! $exceeded) {
                $used += $days;
            }

            /*
             * Лимит сулрах үед (өмнөх мөр устсан, лимит нэмэгдсэн)
             * бидний сольсон мөрийг цалинтай болгож буцаана. Гараар
             * цалингүй гэж сонгосон мөрийг хөндөхгүй.
             */
            $type = match (true) {
                $exceeded => 'tsalingui',
                (bool) $row->limit_exceeded => 'tsalintai',
                default => $row->type,
            };

            if ($row->limit_exceeded !== $exceeded || $row->type !== $type) {
                $row->forceFill([
                    'limit_exceeded' => $exceeded,
                    'type' => $type,
                ])->save();
            }
        }
    }

    /**
     * Лимит өөрчлөгдсөн үед бүх хүн, бүх жилийг дахин тооцно.
     */
    public static function reapplyAll(): void
    {
        Leave::query()
            ->whereNotNull('person_name')
            ->whereNotNull('start_date')
            ->selectRaw('person_name')
            ->distinct()
            ->pluck('person_name')
            ->each(function (string $person): void {
                Leave::query()
                    ->where('person_name', $person)
                    ->whereNotNull('start_date')
                    ->pluck('start_date')
                    ->map(fn ($date) => Carbon::parse($date)->year)
                    ->unique()
                    ->each(fn (int $year) => self::reapply($person, $year));
            });
    }

    /**
     * Мөрийн хүн, жилээр нь дахин тооцно.
     */
    public static function reapplyFor(Leave $leave): void
    {
        $year = $leave->start_date
            ? Carbon::parse($leave->start_date)->year
            : null;

        self::reapply($leave->person_name, $year);
    }
}
