<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Гар утас эсэхийг User-Agent-аар тодорхойлно. Desktop PWA-д биометрик хэрэглэхгүй. */
class MobileClient
{
    public static function isMobileRequest(?Request $request): bool
    {
        $ua = strtolower($request?->userAgent() ?? '');

        if ($ua === '') {
            return false;
        }

        // iPadOS 13+ заримдаа «Macintosh» гэж илгээнэ.
        if (str_contains($ua, 'ipad')
            || (str_contains($ua, 'macintosh') && str_contains($ua, 'mobile'))) {
            return true;
        }

        return str_contains($ua, 'android')
            || str_contains($ua, 'iphone')
            || str_contains($ua, 'ipod')
            || (str_contains($ua, 'mobile') && ! str_contains($ua, 'windows'));
    }
}
