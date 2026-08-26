<?php

namespace App\Support;

use App\Models\TaskSource;

/**
 * Үүрэг даалгаврын Word файлыг хүснэгтийн мөр болгож хөрвүүлнэ.
 *
 * Үүрэг чиглэл:  № | Үүрэг чиглэл | Хариуцах эзэн | Хяналт тавих албан тушаалтан
 * Бэлтгэл ажил:  № | Ажлын чиглэл | Арга хэмжээ | Хугацаа | Хариуцах эзэн | Хамтран хэрэгжүүлэх
 */
class TaskDocxParser
{
    public function __construct(private readonly DocxTableReader $reader) {}

    /**
     * @return array<int, array<string, string|null>>
     */
    public function parse(string $path, string $kind): array
    {
        $rows = [];

        foreach ($this->reader->tables($path) as $table) {
            foreach ($table as $cells) {
                $filled = array_values(array_filter($cells, fn (string $c) => $c !== ''));

                if (! $filled) {
                    continue;
                }

                if ($this->isHeaderRow($cells)) {
                    continue;
                }

                // Нийлүүлсэн ганц нүдтэй мөр — бүлгийн гарчиг. Текст болгон хадгална.
                if (count($filled) === 1) {
                    $rows[] = $this->row(['text' => $filled[0]]);

                    continue;
                }

                $rows[] = $kind === TaskSource::KEY_PREP_PLAN
                    ? $this->prepPlanRow($cells)
                    : $this->directiveRow($cells);
            }
        }

        return array_values(array_filter($rows, fn (array $r) => ($r['text'] ?? '') !== ''));
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $markers = [
            'үүрэг чиглэл',
            'хариуцах эзэн',
            'арга хэмжээ',
            'хяналт тавих',
            'ажлын чиглэл',
            'хугацаа',
            'хамтран хэрэгжүүлэх',
        ];

        $hits = 0;

        foreach ($cells as $cell) {
            $value = mb_strtolower(trim($cell));
            if ($value === '' || $value === '№' || $value === 'no' || $value === '#') {
                continue;
            }

            foreach ($markers as $marker) {
                // Зөвхөн толгойн нүд — урт агуулга дунд гарч ирсэн үгийг header гэж үзэхгүй.
                if ($value === $marker || (str_starts_with($value, $marker) && mb_strlen($value) <= mb_strlen($marker) + 30)) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits >= 2;
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<int, string>
     */
    private function withoutNumber(array $cells): array
    {
        if (isset($cells[0]) && preg_match('/^\d+[.)]?$/u', trim($cells[0]))) {
            array_shift($cells);
        }

        return array_values($cells);
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, string|null>
     */
    private function directiveRow(array $cells): array
    {
        $cells = $this->withoutNumber($cells);

        return $this->row([
            'text' => $cells[0] ?? '',
            'responsible' => $cells[1] ?? null,
            'collaborator' => $cells[2] ?? null,
        ]);
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, string|null>
     */
    private function prepPlanRow(array $cells): array
    {
        $cells = $this->withoutNumber($cells);

        return $this->row([
            'sector' => $cells[0] ?? null,
            'text' => $cells[1] ?? '',
            'period' => $cells[2] ?? null,
            'responsible' => $cells[3] ?? null,
            'collaborator' => $cells[4] ?? null,
        ]);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string|null>
     */
    private function row(array $values): array
    {
        $clean = fn (?string $v, int $limit) => ($v = trim((string) $v)) === ''
            ? null
            : mb_substr($v, 0, $limit);

        return [
            'text' => (string) $clean($values['text'] ?? '', 5000),
            'period' => $clean($values['period'] ?? null, 255),
            'responsible' => $clean($values['responsible'] ?? null, 255),
            'collaborator' => $clean($values['collaborator'] ?? null, 255),
            'sector' => $clean($values['sector'] ?? null, 255),
        ];
    }
}
