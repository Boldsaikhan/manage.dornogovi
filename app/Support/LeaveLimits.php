<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Чөлөө олгох лимит — журмын 5.4-р заалтын хүснэгт.
 *
 * Хөдөлмөрийн дотоод журам өөрчлөгдвөл кодыг хөндөхгүйгээр «Чөлөөний
 * бүртгэл» цэсний тохиргооноос засна. Хадгалаагүй бол анхны жагсаалт.
 */
class LeaveLimits
{
    public const SETTING_KEY = 'leaves.limits';

    /** Жилд олгох цалинтай чөлөөний дээд хоног (журмын 5.4.11). */
    public const PAID_DAYS_KEY = 'leaves.paid_days_per_year';

    public const PAID_DAYS_DEFAULT = 5;

    /** Лимит тоолох давтамж. */
    public const PERIODS = [
        'year' => 'Жилд',
        'quarter' => 'Улиралд',
        'month' => 'Сард',
        'once' => 'Тохиолдол бүрт',
    ];

    /** Цалингийн төлөв — хүснэгтийн сүүлийн багана. */
    public const PAY = [
        'tsalintai' => 'Цалинтай',
        'tsalingui' => 'Цалингүй',
        'none' => '—',
    ];

    /**
     * Журамд заасан анхны лимитүүд.
     *
     * @var list<array{code: string, label: string, period: string, days: int, pay: string}>
     */
    public const DEFAULTS = [
        [
            'code' => '5.4.3',
            'label' => 'Нэгжийн даргын олгох чөлөө',
            'period' => 'quarter',
            'days' => 0,
            'pay' => 'none',
        ],
        [
            'code' => '5.4.4',
            'label' => 'Нэгжийн даргатай зөвшилцөж Тамгын газрын даргын олгох чөлөө',
            'period' => 'year',
            'days' => 5,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.5',
            'label' => 'Эцэг эх, хадам эцэг эх, эхнэр/нөхөр, үр хүүхэд, ач зээ, төрсөн ах эгч дүү, өвөө эмээ нас барах, хүндээр өвчлөх, эмнэлгийн магадлагаагаар асрах шаардлагатай болох, байгалийн гамшигт нэрвэгдэх',
            'period' => 'once',
            'days' => 5,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.6',
            'label' => 'Өөрт болон ар гэрт тохиолдсон баяр ёслол (гэрлэх, шинэ гэр/орон сууцанд орох, ургийн баяр)',
            'period' => 'once',
            'days' => 1,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.7',
            'label' => 'Эхнэр нь хүүхэд төрүүлсэн',
            'period' => 'once',
            'days' => 10,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.8',
            'label' => 'Гэр бүлийн гишүүнд гачигдал зовлон тохиолдож, асрах эсвэл гадаад/дотоод эмчилгээнд авч явах шаардлагатайг эмнэлэг нотолсон',
            'period' => 'once',
            'days' => 10,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.9',
            'label' => 'Хүүхдийн төрсөн өдөр',
            'period' => 'once',
            'days' => 1,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.11',
            'label' => 'Дээрх (5.4.5–5.4.9)-өөс бусад тохиолдолд дарга нарын олгох чөлөө',
            'period' => 'year',
            'days' => 5,
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.12',
            'label' => 'Хувийн зардлаар бакалавр, магистр, докторын зэрэг олгох сургалтад суралцаж буй үед (суралцах, бүтээл хийх)',
            'period' => 'year',
            'days' => 15,
            'pay' => 'tsalintai',
        ],
    ];

    /** @var list<array{code: string, label: string, period: string, days: int, pay: string}>|null */
    private static ?array $cache = null;

    /**
     * Хадгалсан лимитүүд, эс бөгөөс анхны жагсаалт.
     *
     * @return list<array{code: string, label: string, period: string, days: int, pay: string}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $raw = AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
            $decoded = $raw ? json_decode((string) $raw, true) : null;
        } catch (\Throwable $e) {
            // Хүснэгт бэлэн биш (migration-аас өмнө) — анхны утгаар.
            $decoded = null;
        }

        return self::$cache = is_array($decoded)
            ? self::normalize($decoded)
            : self::DEFAULTS;
    }

    /**
     * Жилд олгох цалинтай чөлөөний дээд хоног.
     *
     * 0 бол хязгаарлахгүй.
     */
    public static function paidDaysPerYear(): int
    {
        if (self::$paidDays !== null) {
            return self::$paidDays;
        }

        try {
            $raw = AppSetting::query()->where('key', self::PAID_DAYS_KEY)->value('value');
        } catch (\Throwable $e) {
            $raw = null;
        }

        return self::$paidDays = $raw === null || $raw === ''
            ? self::PAID_DAYS_DEFAULT
            : max(0, (int) $raw);
    }

    public static function savePaidDaysPerYear(int $days): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::PAID_DAYS_KEY],
            ['value' => (string) max(0, $days)],
        );

        self::forget();
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    public static function save(array $rows): void
    {
        $clean = self::normalize($rows);

        AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE)],
        );

        self::forget();
    }

    /** Журмын анхны жагсаалт руу буцаана. */
    public static function reset(): void
    {
        AppSetting::query()
            ->whereIn('key', [self::SETTING_KEY, self::PAID_DAYS_KEY])
            ->delete();

        self::forget();
    }

    public static function forget(): void
    {
        self::$cache = null;
        self::$paidDays = null;
    }

    private static ?int $paidDays = null;

    /**
     * Нэг журмыг кодоор нь олно.
     *
     * @return array{code: string, label: string, period: string, days: int, pay: string}|null
     */
    public static function find(?string $code): ?array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        foreach (self::all() as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }

        return null;
    }

    /**
     * «Жилд ажлын 5 хоног хүртэл» гэх мэт уншихад ойлгомжтой бичвэр.
     *
     * @param  array{period: string, days: int}  $row
     */
    public static function limitText(array $row): string
    {
        $period = self::PERIODS[$row['period']] ?? '';
        $days = (int) ($row['days'] ?? 0);

        if ($days <= 0) {
            return trim($period.' (хоног заагаагүй)');
        }

        return trim($period.' ажлын '.$days.' хоног хүртэл');
    }

    /** Хуучин чөлөөт бичвэрээс давтамжийг таана. */
    private static function guessPeriod(string $text): string
    {
        $text = mb_strtolower($text);

        return match (true) {
            str_contains($text, 'улирал') => 'quarter',
            str_contains($text, 'сард') => 'month',
            str_contains($text, 'жилд') => 'year',
            default => 'once',
        };
    }

    /**
     * Хоосон мөрийг хасч, талбаруудыг цэгцэлнэ.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{code: string, label: string, period: string, days: int, pay: string}>
     */
    private static function normalize(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));

            // Нэргүй мөр утгагүй — алгасна.
            if ($label === '') {
                continue;
            }

            $pay = (string) ($row['pay'] ?? 'tsalintai');
            $period = (string) ($row['period'] ?? '');

            /*
             * Хуучин хадгалсан хэлбэрт «limit» нь чөлөөт бичвэр байсан.
             * Тоог нь салгаж авна, давтамжийг нь бичвэрээс таана.
             */
            if (! array_key_exists($period, self::PERIODS)) {
                $period = self::guessPeriod((string) ($row['limit'] ?? ''));
            }

            $days = array_key_exists('days', $row)
                ? (int) $row['days']
                : (int) (preg_replace('/\D+/', '', (string) ($row['limit'] ?? '')) ?: 0);

            $out[] = [
                'code' => trim((string) ($row['code'] ?? '')),
                'label' => $label,
                'period' => $period,
                'days' => max(0, min(365, $days)),
                'pay' => array_key_exists($pay, self::PAY) ? $pay : 'tsalintai',
            ];
        }

        return $out;
    }
}
