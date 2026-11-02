<?php

namespace App\Support;

/**
 * Салбарын үзүүлэлтийн хэмжих нэгж.
 *
 * Нэгжийг гараар бичихгүй, эндээс сонгоно. Сонгосон нэгж нь
 * «хүрэх түвшин» болон жилийн нүднүүдийн тоон хэлбэрийг заана.
 */
class IndicatorMeasure
{
    /**
     * @return array<string, string> нэгж => integer|percent|money|decimal
     */
    public static function units(): array
    {
        return [
            'тоо' => 'integer',
            'ширхэг' => 'integer',
            'хувь' => 'percent',
            'төгрөг' => 'money',
            'мян.төг' => 'decimal',
            'хүн' => 'integer',
            'айл' => 'integer',
            'удаа' => 'integer',
            'хоног' => 'integer',
            'цаг' => 'decimal',
            'км' => 'decimal',
            'м²' => 'decimal',
            'га' => 'decimal',
            'кг' => 'decimal',
            'тонн' => 'decimal',
            'оноо' => 'decimal',
        ];
    }

    /**
     * @return list<array{value: string, kind: string, suffix: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::units() as $value => $kind) {
            $options[] = [
                'value' => $value,
                'kind' => $kind,
                'suffix' => match ($kind) {
                    'percent' => '%',
                    'money' => '₮',
                    default => '',
                },
            ];
        }

        return $options;
    }

    /** График дээр ашиглах тоо. Тоо биш бол null. */
    public static function numeric(?string $unit, mixed $raw): ?float
    {
        $normalized = self::normalize($unit, $raw);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /** Хүснэгт, самбар дээр харагдах хэлбэр. */
    public static function display(?string $unit, mixed $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        $kind = self::units()[$unit] ?? null;
        $number = self::numeric($unit, $raw);

        if ($kind === null || $number === null) {
            return $raw;
        }

        return match ($kind) {
            'percent' => self::trimNumber($number, 1).'%',
            'money' => number_format($number, 0, '.', ' ').' ₮',
            'integer' => number_format($number, 0, '.', ' '),
            default => self::trimNumber($number, 2),
        };
    }

    /** Сонгосон нэгжид тааруулж тоон утгыг цэвэрлэнэ. */
    public static function normalize(?string $unit, mixed $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        $kind = self::units()[$unit] ?? null;

        if ($kind === null) {
            return $raw;
        }

        $cleaned = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);
        $cleaned = preg_replace('/[^\d.\-]/', '', $cleaned) ?? '';

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return '';
        }

        $number = (float) $cleaned;

        return match ($kind) {
            'integer', 'money' => (string) (int) round($number),
            'percent' => self::trimNumber($number, 1),
            default => self::trimNumber($number, 2),
        };
    }

    private static function trimNumber(float $number, int $decimals): string
    {
        $formatted = number_format($number, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
