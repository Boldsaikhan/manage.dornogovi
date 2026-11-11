<?php

namespace App\Support;

use App\Models\Leave;

/**
 * Чөлөөний хуудасны дугаар — бүртгэлийн хүснэгтийн дэс дугаар.
 *
 * Хүснэгт нь шинэ бүртгэлийг дээр нь тавьж, дэс дугаарыг доороос
 * дээш тоолдог. Өөрөөр хэлбэл дэс дугаар нь тухайн таб дотор уг
 * мөрөөс өмнө нэмэгдсэн бүртгэлийн тоо + 1 байна.
 *
 * Гараар дугаар бичсэн бол түүнийг нь хүндэтгэнэ.
 */
class LeaveSlipNumber
{
    public static function for(Leave $leave): string
    {
        $manual = trim((string) $leave->slip_number);

        if ($manual !== '') {
            return $manual;
        }

        return (string) self::sequence($leave);
    }

    /** Хүснэгтэд харагдах дэс дугаар. */
    public static function sequence(Leave $leave): int
    {
        if (! $leave->exists) {
            return 1;
        }

        return Leave::query()
            ->where('scope', $leave->scope)
            ->where('id', '<=', $leave->id)
            ->count();
    }
}
