<?php

namespace App\Support;

/**
 * Хэрэгжилтийн хувийг томъёогоор бодно.
 *
 * Мөр бүрт томъёог сонгоно. Сонгоогүй бол хувийг гараар бичнэ.
 */
class ReportFormula
{
    /** Өсөх үзүүлэлт: гүйцэтгэл зорилтод хэдэн хувь хүрсэн бэ. */
    public const GROWTH = 'actual_over_target';

    /**
     * Суурь түвшнээс хэдийг ахисан бэ:
     * (гүйцэтгэл − суурь) ÷ (зорилт − суурь) × 100.
     */
    public const OVER_BASELINE = 'over_baseline';

    /** Гараар бичнэ — тоогоор хэмжигдэхгүй арга хэмжээ. */
    public const MANUAL = '';

    /**
     * Сонгох боломжтой томъёонууд.
     *
     * @return array<int, array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return [
            [
                'value' => self::MANUAL,
                'label' => 'Гараар бичих',
                'hint' => 'Хувийг өөрөө бичнэ — томъёогоор бодохгүй.',
            ],
            [
                'value' => self::GROWTH,
                'label' => 'Гүйцэтгэл ÷ Зорилт × 100',
                'hint' => 'Өсөх үзүүлэлт. 100%-иас дээшхийг 100 болгож таслана.',
            ],
            [
                'value' => self::OVER_BASELINE,
                'label' => '(Гүйцэтгэл − Суурь) ÷ (Зорилт − Суурь) × 100',
                'hint' => 'Суурь түвшнээс эхлэн хэмжинэ. Суурь нь зорилттой тэнцүү бол бодохгүй.',
            ],
        ];
    }

    public static function isKnown(?string $formula): bool
    {
        return in_array((string) $formula, array_column(self::options(), 'value'), true);
    }

    /**
     * Хувийг бодно. Бодох боломжгүй бол null.
     */
    public static function percent(
        ?string $formula,
        mixed $actual,
        mixed $target,
        mixed $baseline = null,
    ): ?float {
        $formula = (string) $formula;
        $actual = self::number($actual);
        $target = self::number($target);

        if ($actual === null || $target === null) {
            return null;
        }

        if ($formula === self::GROWTH) {
            if ($target == 0.0) {
                return null;
            }

            return self::clamp(($actual / $target) * 100);
        }

        if ($formula === self::OVER_BASELINE) {
            $baseline = self::number($baseline) ?? 0.0;
            $span = $target - $baseline;

            // Суурь нь зорилттойгоо тэнцүү бол ахиц хэмжих боломжгүй.
            if ($span == 0.0) {
                return null;
            }

            return self::clamp((($actual - $baseline) / $span) * 100);
        }

        return null;
    }

    /** 0–100 хооронд барина — 150% гэж тайлагнахаас сэргийлнэ. */
    private static function clamp(float $percent): float
    {
        return round(max(0, min(100, $percent)), 1);
    }

    /**
     * Бичсэн утгыг тоо болгоно: «1 200», «1,200.5», «85%» бүгд ажиллана.
     */
    public static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        // Зай, таслал, хувийн тэмдэг, үсгийг хасна.
        $clean = preg_replace('/[^0-9.\-]/u', '', str_replace(',', '', $text));

        if ($clean === null || $clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }
}
