<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Огноог албан бичгийн хэлбэрт оруулна: «2026 оны 08 дугаар сарын 31-ний».
 *
 * Сарын нөхцөл (дугаар/дүгээр) болон өдрийн нөхцөл (-ны/-ний) нь тухайн
 * тоог монголоор дуудахад гарах эгшгийн зохицлоос хамаарна — жишээ нь
 * «найм» (аман хатуу) → «дугаар», «ес» (зөөлөн) → «дүгээр».
 */
class MongolianOrdinal
{
    /** 1-12 сарын нөхцөл. */
    private const MONTH_SUFFIX = [
        1 => 'дүгээр', 2 => 'дугаар', 3 => 'дугаар', 4 => 'дүгээр',
        5 => 'дугаар', 6 => 'дугаар', 7 => 'дугаар', 8 => 'дугаар',
        9 => 'дүгээр', 10 => 'дугаар', 11 => 'дүгээр', 12 => 'дугаар',
    ];

    /** Өдрийн нэгжийн орон (0-9) — аравтын дугаараас үл хамааран сүүлийн цифрээр шийднэ. */
    private const DAY_UNIT_SUFFIX = [
        0 => 'ны', 1 => 'ний', 2 => 'ны', 3 => 'ны', 4 => 'ний',
        5 => 'ны', 6 => 'ны', 7 => 'ны', 8 => 'ны', 9 => 'ний',
    ];

    public static function monthSuffix(int $month): string
    {
        return self::MONTH_SUFFIX[$month] ?? 'дугаар';
    }

    public static function daySuffix(int $day): string
    {
        return self::DAY_UNIT_SUFFIX[$day % 10] ?? 'ны';
    }

    /** «2026 оны 08 дугаар сарын 31-ний өдөр» — өдрийн үгийг залгахгүй, зөвхөн нөхцөлийг буцаана. */
    public static function format(Carbon $date): string
    {
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        return sprintf(
            '%s оны %02d %s сарын %d-%s',
            $date->format('Y'),
            $month,
            self::monthSuffix($month),
            $day,
            self::daySuffix($day),
        );
    }
}
