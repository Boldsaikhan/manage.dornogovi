<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneDirectoryEntry extends Model
{
    /** Чөлөөний бүртгэлийн хамрах хүрээтэй нийцсэн ангилал. */
    public const CATEGORIES = [
        'udirdlaga' => 'Аймгийн удирдлагууд',
        'heltes' => 'Хэлтэс',
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
     * Хэлтэс нь АЗДТГ-ын албан хаагчдын нэгжид хамаарах тул энд ангилахгүй.
     */
    public static function guessCategory(?string $orgName): ?string
    {
        $name = mb_strtolower((string) $orgName);

        // Хэлтэс/хэлтсийн — утасны жагсаалтын ангилал биш, АЗДТГ нэгж.
        if (str_contains($name, 'хэлтэс') || str_contains($name, 'хэллтсийн')) {
            return null;
        }

        if (str_contains($name, 'удирдлага')) {
            return 'udirdlaga';
        }

        if (str_contains($name, 'сум')) {
            return 'sum';
        }

        if (str_contains($name, 'агентлаг') || self::isKnownAgency($orgName)) {
            return 'agentlag';
        }

        return 'baiguullaga';
    }

    /** Нэр нь хэлтэс (АЗДТГ нэгж)-ийг илэрхийлж байгаа эсэх. */
    public static function looksLikeDepartment(?string $orgName): bool
    {
        $name = mb_strtolower(trim((string) $orgName));

        return str_contains($name, 'хэлтэс') || str_contains($name, 'хэллтсийн');
    }

    /**
     * SheetCell / сонголтын жагсаалтад зориулсан богино нэрс.
     *
     * @return array<int, array{value: string, label: string, hint: string, org: string, category: string, full: string}>
     */
    public static function peopleOptions(): array
    {
        $items = [];

        static::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['person_name', 'position', 'org_name', 'category'])
            ->each(function (self $row) use (&$items) {
                $full = trim((string) $row->person_name);
                $name = \App\Support\PersonName::short($full);

                if ($name === '') {
                    return;
                }

                $items[$name] = [
                    'value' => $name,
                    'label' => $name,
                    'hint' => trim((string) $row->position),
                    'org' => trim((string) $row->org_name),
                    'category' => $row->category ?: (self::guessCategory($row->org_name) ?: 'baiguullaga'),
                    'full' => $full,
                ];
            });

        return array_values($items);
    }

    /**
     * Хандах эрх өгөхөд утасны жагсаалтаас сонгох (нэр + утас + албан тушаал).
     *
     * @return array<int, array{id: int, value: string, label: string, hint: string, org: string, category: string, phone: ?string, position: string, full_name: string}>
     */
    public static function accountPeopleOptions(): array
    {
        return static::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'person_name', 'position', 'org_name', 'category', 'office_phone', 'mobile_phone'])
            ->map(function (self $row) {
                $full = trim((string) $row->person_name);
                $short = \App\Support\PersonName::short($full) ?: $full;

                if ($short === '') {
                    return null;
                }

                $phone = self::preferredPhone($row->mobile_phone, $row->office_phone);
                $position = trim((string) $row->position);
                $org = trim((string) $row->org_name);

                return [
                    'id' => $row->id,
                    'value' => $short,
                    'label' => $short,
                    'hint' => $position !== '' ? $position : ($phone ?? ''),
                    'org' => $org,
                    'category' => $row->category ?: (self::guessCategory($row->org_name) ?: 'baiguullaga'),
                    'phone' => $phone,
                    'position' => $position,
                    'full_name' => $full,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Нэвтрэх утастай таарах утасны жагсаалтын нэрс (бүтэн + богино).
     *
     * @return list<string>
     */
    public static function namesMatchingUser(\App\Models\User $user): array
    {
        $phone = \App\Models\User::normalizePhone($user->phone);

        if ($phone === null) {
            return [];
        }

        $names = [];

        static::query()
            ->get(['person_name', 'mobile_phone', 'office_phone'])
            ->each(function (self $row) use ($phone, &$names): void {
                $mobile = \App\Models\User::normalizePhone($row->mobile_phone);
                $office = \App\Models\User::normalizePhone($row->office_phone);

                if ($mobile !== $phone && $office !== $phone) {
                    return;
                }

                $full = trim((string) $row->person_name);
                if ($full === '') {
                    return;
                }

                $names[] = $full;
                $short = \App\Support\PersonName::short($full);
                if ($short !== '' && $short !== $full) {
                    $names[] = $short;
                }
            });

        return array_values(array_unique($names));
    }

    public static function preferredPhone(?string $mobile, ?string $office): ?string
    {
        foreach ([$mobile, $office] as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }

            // Зөвхөн цифр / + үлдээнэ — users.phone max 20.
            $clean = preg_replace('/[^\d+]/', '', $raw) ?: $raw;
            $clean = mb_substr($clean, 0, 20);

            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
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

        foreach (self::agencyNames() as $agency) {
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
     * Агентлагуудын жагсаалт.
     *
     * config:cache хуучирсан үед (deploy-ийн үед тохиолдож болно) файлаас шууд уншина.
     *
     * @return array<int, string>
     */
    private static function agencyNames(): array
    {
        $names = (array) config('agencies.names', []);

        if ($names) {
            return $names;
        }

        $path = config_path('agencies.php');

        if (! is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return (array) ($loaded['names'] ?? []);
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
