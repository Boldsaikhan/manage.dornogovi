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

    /** Цалингийн төлөв — хүснэгтийн сүүлийн багана. */
    public const PAY = [
        'tsalintai' => 'Цалинтай',
        'tsalingui' => 'Цалингүй',
        'none' => '—',
    ];

    /**
     * Журамд заасан анхны лимитүүд.
     *
     * @var list<array{code: string, label: string, limit: string, pay: string}>
     */
    public const DEFAULTS = [
        [
            'code' => '5.4.3',
            'label' => 'Нэгжийн даргын олгох чөлөө',
            'limit' => 'Улиралд 1 удаа (хоног заагаагүй)',
            'pay' => 'none',
        ],
        [
            'code' => '5.4.4',
            'label' => 'Нэгжийн даргатай зөвшилцөж Тамгын газрын даргын олгох чөлөө',
            'limit' => 'Жилд ажлын 2–5 хоног хүртэл',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.5',
            'label' => 'Эцэг эх, хадам эцэг эх, эхнэр/нөхөр, үр хүүхэд, ач зээ, төрсөн ах эгч дүү, өвөө эмээ нас барах, хүндээр өвчлөх, эмнэлгийн магадлагаагаар асрах шаардлагатай болох, байгалийн гамшигт нэрвэгдэх',
            'limit' => 'Ажлын 5 хоног хүртэл',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.6',
            'label' => 'Өөрт болон ар гэрт тохиолдсон баяр ёслол (гэрлэх, шинэ гэр/орон сууцанд орох, ургийн баяр)',
            'limit' => '1 өдөр',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.7',
            'label' => 'Эхнэр нь хүүхэд төрүүлсэн',
            'limit' => 'Ажлын 10 хоног',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.8',
            'label' => 'Гэр бүлийн гишүүнд гачигдал зовлон тохиолдож, асрах эсвэл гадаад/дотоод эмчилгээнд авч явах шаардлагатайг эмнэлэг нотолсон',
            'limit' => 'Ажлын 10 хоног хүртэл',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.9',
            'label' => 'Хүүхдийн төрсөн өдөр',
            'limit' => '1 өдөр',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.11',
            'label' => 'Дээрх (5.4.5–5.4.9)-өөс бусад тохиолдолд дарга нарын олгох чөлөө',
            'limit' => 'Жилд ажлын 5 хоногоос илүүгүй',
            'pay' => 'tsalintai',
        ],
        [
            'code' => '5.4.12',
            'label' => 'Хувийн зардлаар бакалавр, магистр, докторын зэрэг олгох сургалтад суралцаж буй үед (суралцах, бүтээл хийх)',
            'limit' => 'Жилд 1 удаа, ажлын 15 хоног хүртэл',
            'pay' => 'tsalintai',
        ],
    ];

    /** @var list<array{code: string, label: string, limit: string, pay: string}>|null */
    private static ?array $cache = null;

    /**
     * Хадгалсан лимитүүд, эс бөгөөс анхны жагсаалт.
     *
     * @return list<array{code: string, label: string, limit: string, pay: string}>
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
        AppSetting::query()->where('key', self::SETTING_KEY)->delete();

        self::forget();
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /**
     * Хоосон мөрийг хасч, талбаруудыг цэгцэлнэ.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{code: string, label: string, limit: string, pay: string}>
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

            $out[] = [
                'code' => trim((string) ($row['code'] ?? '')),
                'label' => $label,
                'limit' => trim((string) ($row['limit'] ?? '')),
                'pay' => array_key_exists($pay, self::PAY) ? $pay : 'tsalintai',
            ];
        }

        return $out;
    }
}
