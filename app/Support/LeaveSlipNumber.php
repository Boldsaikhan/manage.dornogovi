<?php

namespace App\Support;

use App\Models\Leave;
use Illuminate\Support\Facades\DB;

/**
 * Чөлөөний хуудасны дугаар — жил бүр 1-ээс эхэлж автоматаар өснө.
 *
 * Дугаарыг хэвлэх үед нь л олгоно: хэвлээгүй мөр дугаар эзлэхгүй.
 * Гараар бичсэн дугаарыг хэзээ ч дарж бичихгүй.
 */
class LeaveSlipNumber
{
    /**
     * Дугааргүй бол шинийг олгож, байгаа бол хэвээр нь буцаана.
     */
    public static function ensure(Leave $leave): string
    {
        $current = trim((string) $leave->slip_number);

        if ($current !== '') {
            return $current;
        }

        $year = (int) ($leave->start_date?->format('Y') ?: now()->format('Y'));

        return DB::transaction(function () use ($leave, $year) {
            // Гүйлгээ дотор дахин уншина — зэрэг хэвлэхэд давхцахгүй.
            $fresh = Leave::query()->lockForUpdate()->find($leave->id);
            $existing = trim((string) ($fresh?->slip_number ?? ''));

            if ($existing !== '') {
                $leave->slip_number = $existing;

                return $existing;
            }

            $number = (string) (self::lastNumber($year) + 1);

            $fresh?->forceFill(['slip_number' => $number])->save();
            $leave->slip_number = $number;

            return $number;
        });
    }

    /**
     * Тухайн жилийн хамгийн том дугаар. Зөвхөн бүхэл тоон дугаарыг тооцно —
     * гараар «А-12» мэтээр бичсэн нь дарааллыг эвдэхгүй.
     */
    private static function lastNumber(int $year): int
    {
        $numbers = Leave::query()
            ->whereNotNull('slip_number')
            ->whereYear('start_date', $year)
            ->pluck('slip_number');

        $max = 0;

        foreach ($numbers as $value) {
            $text = trim((string) $value);

            if ($text !== '' && ctype_digit($text)) {
                $max = max($max, (int) $text);
            }
        }

        return $max;
    }
}
