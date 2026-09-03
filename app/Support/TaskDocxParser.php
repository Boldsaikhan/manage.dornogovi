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
     * Word файлын эхний утга бүхий хүснэгтийг ТҮҮХИЙГЭЭР нь буцаана.
     *
     * Багана тааруулах цонхонд хэрэглэнэ — аль Word багана нь хүснэгтийн аль
     * толгойд орохыг хэрэглэгч өөрөө сонгоно.
     *
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function rawTable(string $path): array
    {
        foreach ($this->reader->tables($path) as $table) {
            $headers = [];
            $rows = [];

            foreach ($table as $cells) {
                $cells = array_map(fn ($c) => trim((string) $c), array_values($cells));

                if (! array_filter($cells, fn (string $c) => $c !== '')) {
                    continue;
                }

                if (! $headers && $this->isHeaderRow($cells)) {
                    $headers = $cells;

                    continue;
                }

                $rows[] = $cells;
            }

            if ($rows) {
                return ['headers' => $headers, 'rows' => $rows];
            }
        }

        return ['headers' => [], 'rows' => []];
    }

    /**
     * Word-ийн толгойн нэрээр багануудыг таамаглана.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $columnKeys  хүснэгтийн толгойн түлхүүрүүд
     * @return array<string, int|null>  түлхүүр => Word баганын индекс
     */
    public static function guessMapping(array $headers, array $columnKeys): array
    {
        $normalize = static fn (string $v) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($v))) ?: '';

        // Толгойн нэршлийн хувилбарууд.
        $synonyms = [
            TaskSource::COLUMN_SECTOR => ['ажлынчиглэл', 'чиглэл', 'салбар'],
            TaskSource::COLUMN_MEASURE => ['аргахэмжээ', 'хэрэгжүүлэхаргахэмжээ'],
            TaskSource::COLUMN_TEXT => ['үүрэгчиглэл', 'үүрэгдаалгавар', 'агуулга', 'заалт'],
            TaskSource::COLUMN_PERIOD => ['хугацаа', 'хэрэгжүүлэххугацаа'],
            TaskSource::COLUMN_RESPONSIBLE => ['хариуцахэзэн', 'хариуцагч', 'хариуцах'],
            TaskSource::COLUMN_COLLABORATOR => ['хяналттавих', 'хяналттавихалбантушаалтан', 'хамтранхэрэгжүүлэх', 'хяналт'],
            TaskSource::COLUMN_NOTE => ['хэрэгжилт', 'биелэлт', 'тайлбар'],
        ];

        $catalog = [];
        foreach (self::catalogLabels() as $key => $label) {
            $catalog[$key] = $normalize($label);
        }

        $normalized = array_map($normalize, $headers);
        $used = [];
        $mapping = [];

        foreach ($columnKeys as $key) {
            $candidates = array_merge(
                isset($catalog[$key]) ? [$catalog[$key]] : [],
                $synonyms[$key] ?? [],
            );

            $found = null;

            foreach ($normalized as $index => $header) {
                if ($header === '' || in_array($index, $used, true)) {
                    continue;
                }

                foreach ($candidates as $candidate) {
                    if ($candidate !== '' && ($header === $candidate || str_contains($header, $candidate))) {
                        $found = $index;

                        break 2;
                    }
                }
            }

            if ($found !== null) {
                $used[] = $found;
            }

            $mapping[$key] = $found;
        }

        // Толгой олдоогүй бол дараалуулан онооно (№ баганыг алгасна).
        if (! array_filter($mapping, fn ($v) => $v !== null)) {
            $offset = self::looksLikeNumberColumn($headers, 0) ? 1 : 0;

            foreach (array_values($columnKeys) as $i => $key) {
                $mapping[$key] = $i + $offset;
            }
        }

        return $mapping;
    }

    /**
     * Тааруулалтын дагуу түүхий мөрүүдийг хүснэгтийн мөр болгоно.
     *
     * @param  list<list<string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return array<int, array<string, string|null>>
     */
    public function rowsFromMapping(array $rows, array $mapping): array
    {
        $out = [];

        foreach ($rows as $cells) {
            $values = [];

            foreach ($mapping as $key => $index) {
                $values[$key] = $index === null ? null : ($cells[$index] ?? null);
            }

            $row = $this->row($values);

            // Нийлүүлсэн ганц нүдтэй мөр — бүлгийн гарчиг. «text»-д хадгална.
            if ($row['text'] === '') {
                $filled = array_values(array_filter($cells, fn (string $c) => $c !== ''));

                if (count($filled) === 1) {
                    $row['text'] = $filled[0];
                }
            }

            // Тааруулсан талбаруудын аль нэг нь дүүрсэн бол мөрийг авна.
            // (Толгойд «Үүрэг чиглэл» байхгүй хүснэгт ч байж болно.)
            $hasValue = collect($row)->contains(fn ($value) => trim((string) $value) !== '');

            if ($hasValue) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function catalogLabels(): array
    {
        $labels = [];

        foreach (TaskSource::columnCatalog() as $column) {
            $labels[$column['key']] = $column['label'];
        }

        return $labels;
    }

    /** @param  list<string>  $headers */
    private static function looksLikeNumberColumn(array $headers, int $index): bool
    {
        $value = mb_strtolower(trim($headers[$index] ?? ''));

        return in_array($value, ['№', 'no', '#', 'д/д', 'дд'], true);
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
            'measure' => $clean($values['measure'] ?? null, 5000),
            'period' => $clean($values['period'] ?? null, 255),
            'responsible' => $clean($values['responsible'] ?? null, 255),
            'collaborator' => $clean($values['collaborator'] ?? null, 255),
            'sector' => $clean($values['sector'] ?? null, 255),
            'note' => $clean($values['note'] ?? null, 5000),
        ];
    }
}
