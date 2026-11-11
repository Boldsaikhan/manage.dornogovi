<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ажлын өдрөөр тоолох.
 *
 * Чөлөөний хугацаанд долоо хоногийн амралтын өдөр (Бямба, Ням)
 * тооцогдохгүй: «2 хоногийн чөлөө» гэдэг нь ажлын хоёр өдөр.
 */
class WorkDays
{
    /** Долоо хоногийн амралтын өдөр эсэх. */
    public static function isRestDay(Carbon $date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    /**
     * Эхлэх өдрөөс хойш ажлын N дэх өдөр.
     *
     * Эхлэх өдрийг өөрийг нь оролцуулж тоолно. Эхлэх огноо амралтын
     * өдөр таарвал дараагийн ажлын өдрөөс эхэлнэ.
     */
    public static function endDate(Carbon|string $start, int $days): Carbon
    {
        $cursor = ($start instanceof Carbon ? $start->copy() : Carbon::parse($start))->startOfDay();

        while (self::isRestDay($cursor)) {
            $cursor->addDay();
        }

        for ($counted = 1; $counted < max(1, $days); $counted++) {
            do {
                $cursor->addDay();
            } while (self::isRestDay($cursor));
        }

        return $cursor;
    }
}
