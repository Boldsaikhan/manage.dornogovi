<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneDirectoryEntry extends Model
{
    /** Чөлөөний бүртгэлийн хамрах хүрээтэй нийцсэн ангилал. */
    public const CATEGORIES = [
        'agentlag' => 'Агентлаг',
        'sum' => 'Сумд',
        'baiguullaga' => 'Байгууллага',
    ];

    protected $fillable = [
        'org_name', 'category', 'org_order', 'sort_order', 'person_name', 'position',
        'office_phone', 'mobile_phone',
    ];

    /**
     * Байгууллагын нэрээр ангиллыг таамаглана — дараа нь гараар засаж болно.
     */
    public static function guessCategory(?string $orgName): string
    {
        $name = mb_strtolower((string) $orgName);

        if (str_contains($name, 'сум')) {
            return 'sum';
        }

        if (str_contains($name, 'агентлаг') || self::isKnownAgency($orgName)) {
            return 'agentlag';
        }

        return 'baiguullaga';
    }

    /**
     * config/agencies.php дахь аймгийн агентлагуудтай тулгана.
     */
    public static function isKnownAgency(?string $orgName): bool
    {
        $needle = self::normalizeName($orgName);

        if (mb_strlen($needle) < 8) {
            return false;
        }

        foreach ((array) config('agencies.names', []) as $agency) {
            $candidate = self::normalizeName($agency);

            if ($candidate === '' ) {
                continue;
            }

            // Нэр нь бүтнээрээ эсвэл хэсэгчлэн (жишээ нь «Аудитын газар») таарч болно.
            if (str_contains($needle, $candidate) || str_contains($candidate, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Харьцуулахад саад болох тэмдэгт, тайлбарыг цэвэрлэнэ.
     */
    private static function normalizeName(?string $name): string
    {
        $value = mb_strtolower(trim((string) $name));

        // «/Татан буугдсан/» гэх мэт налуу зураас доторх тайлбарыг хасна.
        $value = (string) preg_replace('#/[^/]*/#u', ' ', $value);
        $value = (string) preg_replace('/^\d+[.\)]\s*/u', '', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);

        return $value;
    }
}
