<?php

namespace App\Support;

use App\Models\Department;
use App\Models\IndicatorPeriod;
use App\Models\SectorIndicator;
use App\Models\SectorIndicatorValue;
use Illuminate\Support\Collection;

/**
 * Төрийн захиргааны удирдлагын хэлтсийн салбарын үзүүлэлт.
 *
 * Жилийн нүд: 2021, 2022, 2023, дараа нь 2024–2026 он тус бүр
 * «хагас жил» ба «жилийн эцэс». Хоосон нүдийг алгасна.
 * Дахин ажиллуулахад байгаа мөрийг нэрээр нь олж нөхнө.
 */
class SectorIndicatorCatalog
{
    public const UNIT = 'Төрийн захиргааны удирдлагын хэлтэс';

    /**
     * Жил ба нүд. 2024 оноос хойш хагас жил, жилийн эцэс ээлжилнэ.
     *
     * @var list<array{0: int, 1: string}>
     */
    private const SLOTS = [
        [2021, 'year'],
        [2022, 'year'],
        [2023, 'year'],
        [2024, 'half'],
        [2024, 'year'],
        [2025, 'half'],
        [2025, 'year'],
        [2026, 'half'],
        [2026, 'year'],
    ];

    /**
     * Нэр, хэмжих нэгж, хүрэх түвшин, 9 жилийн нүд.
     *
     * @return list<array{title: string, unit: ?string, target: ?string, values: list<?string>}>
     */
    public static function rows(): array
    {
        $n = fn (...$values) => array_map(
            fn ($value) => $value === null ? null : (string) $value,
            $values,
        );

        return [
            ['title' => 'ERP системд бүртгэсэн байгууллагын тоо', 'unit' => 'тоо', 'target' => '180', 'values' => $n(55, 128, 145, 148, 150, 174, 178, 179, null)],
            ['title' => 'ERP системийг дотооддоо ашиглаж буй байгууллагын тоо', 'unit' => 'тоо', 'target' => '180', 'values' => $n(28, 60, 71, null, 75, null, 128, 140, null)],
            ['title' => 'Аймгийн e-mongolia.mn-д орсон үйлчилгээний тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, 75, 83, 78, 78, 78, 78, 143, null)],
            ['title' => '«ХУРДАН» төвөөр үзүүлэх үйлчилгээний төрлийн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(28, 353, 353, 353, 353, 451, 451, 600, null)],
            ['title' => 'Нийт цахимд шилжсэн үйлчилгээний тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, 1800, null, 180, 922, null, 849, null)],
            ['title' => 'e-mongolia.mn-аар үзүүлсэн үйлчилгээний тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(2202, 1650, 12461, 9200, 11000, 8182, 28025, 5387, null)],
            ['title' => 'Call center үйлчилгээний дуудлага авсан тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, 1200, 620, 1143, 540, 1230, null, null)],
            ['title' => 'КИОСК төхөөрөмжөөр үйлчлүүлсэн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(4510, 5579, null, 832, 1158, null, 12358, 9152, null)],
            ['title' => 'КИОСК төхөөрөмжийн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, 2, null, 7, null, 19, 22, null)],
            ['title' => 'Нийт цахимаар үзүүлсэн үйлчилгээний тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, 104709, 130939, 98087, 139025, 95132, 151450, 14539, null)],
            ['title' => 'Тоон гарын үсэг авсан иргэний тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, 1915, 3988, 5777, 9765, 4036, 4868, 3321, null)],
            ['title' => 'Тоо гарын үсэгтэй албан хаагчийн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, 178, 450, 455, 460, 470, 474, 540, null)],
            ['title' => 'shilen.gov.mn сайтын үнэлгээний хувь', 'unit' => 'хувь', 'target' => null, 'values' => $n(null, null, null, null, 80, 100, null, null, null)],
            ['title' => 'huduu.mn сайтын мэдээлэл шинэчилсэн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, null, null, null, 158, null, null, null)],
            ['title' => 'Салбар нэгжүүдийн явцын үнэлгээний хувь', 'unit' => 'хувь', 'target' => null, 'values' => $n(null, null, null, null, null, null, null, null, null)],
            ['title' => 'ХУРДАН төвөөр үйлчлүүлэгчдийн сэтгэл ханамжийн үнэлгээ', 'unit' => null, 'target' => null, 'values' => $n(null, null, null, null, null, null, null, null, null)],
            ['title' => 'Салбараар үйлчлүүлэгчдийн сэтгэл ханамжийн үнэлгээний тоо', 'unit' => 'хувь', 'target' => null, 'values' => $n(null, null, null, null, null, 90, null, null, null)],
            ['title' => 'Салбарын ажилтны хүний нөөцийн шинэчлэгдсэн талаарх мэдээлэл', 'unit' => null, 'target' => null, 'values' => $n(null, null, null, null, null, null, null, null, null)],
            ['title' => 'Сургалт хийсэн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, null, null, null, 15, null, null, null)],
            ['title' => 'Сургалтад хамруулсан иргэн, аж ахуйн тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, null, null, 7045, null, 8057, null, null)],
            ['title' => 'Цахим Дорноговь хөтөлбөрийн тайлан', 'unit' => 'хувь', 'target' => '100', 'values' => $n(null, null, 59, null, 71, null, 81, null, null)],
            ['title' => 'ЦХНХХС-н гэрээний хэрэгжилт', 'unit' => 'хувь', 'target' => '100', 'values' => $n(null, '92.7', '94.7', null, '92.1', null, '86.5', null, null)],
            ['title' => 'Цахим хуудастай байгууллагын тоо', 'unit' => 'тоо', 'target' => '180', 'values' => $n(48, 58, 69, 81, 98, 138, 138, null, null)],
            ['title' => 'Орон нутгийн онцлогтой цахимжсан үйлчилгээ', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, 78, 78, 78, 78, 78, 78, null)],
            ['title' => 'Кибер аюулгүй байдлын журамтай байгууллагын тоо', 'unit' => 'тоо', 'target' => null, 'values' => $n(null, null, null, null, null, null, null, null, null)],
        ];
    }

    public static function import(): void
    {
        $department = Department::query()->where('name', self::UNIT)->first()
            ?? Department::query()->where('name', 'like', '%Төрийн захиргааны удирдлагын%')->orderBy('id')->first();

        if (! $department) {
            return;
        }

        $periods = self::periods();
        $pool = SectorIndicator::query()
            ->where('department_id', $department->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach (self::rows() as $index => $row) {
            $indicator = self::takeMatch($pool, $row['title']);

            $payload = [
                'department_id' => $department->id,
                'unit_name' => $department->name,
                'title' => $row['title'],
                'measure_unit' => $row['unit'],
                'target' => self::number($row['unit'], $row['target']),
                'sort_order' => $index + 1,
            ];

            if ($indicator) {
                $indicator->update($payload);
            } else {
                $indicator = SectorIndicator::create($payload);
            }

            foreach (self::SLOTS as $slotIndex => [$year, $slot]) {
                $value = self::number($row['unit'], $row['values'][$slotIndex] ?? null);

                if ($value === null) {
                    continue;
                }

                SectorIndicatorValue::query()->updateOrCreate(
                    [
                        'sector_indicator_id' => $indicator->id,
                        'indicator_period_id' => $periods[$year]->id,
                        'slot' => $slot,
                    ],
                    ['value' => $value],
                );
            }
        }
    }

    /**
     * @return array<int, IndicatorPeriod>
     */
    private static function periods(): array
    {
        $periods = [];

        foreach ([2021, 2022, 2023, 2024, 2025, 2026] as $year) {
            $period = IndicatorPeriod::query()
                ->where(function ($query) use ($year) {
                    $query->where('year', $year)
                        ->orWhere('label', (string) $year)
                        ->orWhere('label', $year.' он');
                })
                ->orderBy('id')
                ->first();

            $split = $year >= 2024;

            if (! $period) {
                $period = IndicatorPeriod::create([
                    'label' => (string) $year,
                    'year' => $year,
                    'split' => $split,
                    'sort_order' => $year,
                ]);
            } else {
                $changes = [];

                if (! $period->year) {
                    $changes['year'] = $year;
                }

                if ($split && ! $period->split) {
                    $changes['split'] = true;
                }

                if ($changes !== []) {
                    $period->update($changes);
                }
            }

            $periods[$year] = $period->fresh();
        }

        return $periods;
    }

    /**
     * @param  Collection<int, SectorIndicator>  $pool
     */
    private static function takeMatch(Collection $pool, string $title): ?SectorIndicator
    {
        $wanted = self::key($title);
        $best = null;
        $bestLength = -1;

        foreach ($pool as $row) {
            $have = self::key($row->title);

            if ($have === '') {
                continue;
            }

            $matches = $have === $wanted
                || (mb_strlen($have) >= 12 && (str_starts_with($wanted, $have) || str_starts_with($have, $wanted)));

            if (! $matches || mb_strlen($have) <= $bestLength) {
                continue;
            }

            $best = $row;
            $bestLength = mb_strlen($have);
        }

        if ($best) {
            $pool->forget($pool->search(fn (SectorIndicator $row) => $row->id === $best->id));
        }

        return $best;
    }

    private static function key(?string $title): string
    {
        $title = mb_strtolower(trim((string) $title));
        $title = str_replace(['«', '»', '"', '“', '”', "'"], '', $title);

        return preg_replace('/\s+/u', ' ', $title) ?? $title;
    }

    private static function number(?string $unit, ?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $value = IndicatorMeasure::normalize($unit, $raw);

        return $value === '' ? null : $value;
    }
}
