<?php

namespace App\Support;

/**
 * Word (.docx) файлын хүснэгтээс утасны жагсаалтыг уншина.
 *
 * Ерөнхий толгой: № | Овог нэр | Албан тушаал | Ажлын өрөөний утас | Гар утас
 * Сумын жагсаалт ихэвчлэн: № | Албан тушаал | Овог нэр | Утас …
 */
class PhoneDirectoryDocxParser
{
    /** @var 'name_first'|'position_first' */
    private string $columnOrder = 'name_first';

    public function __construct(private readonly DocxTableReader $reader) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        $rows = [];
        $orgName = '';
        $orgOrder = 0;
        $sortOrder = 0;
        $this->columnOrder = 'name_first';

        foreach ($this->reader->tables($path) as $table) {
            foreach ($table as $cells) {
                $cells = array_map(
                    fn (string $c) => trim((string) preg_replace('/\s+/u', ' ', $c)),
                    $cells
                );

                $filled = array_values(array_filter($cells, fn (string $c) => $c !== ''));

                if (! $filled) {
                    continue;
                }

                // Нийлүүлсэн ганц утгатай мөр — байгууллагын нэр.
                if (count($filled) === 1) {
                    $orgName = $filled[0];
                    $orgOrder++;
                    $sortOrder = 0;
                    $this->columnOrder = $this->isSumOrg($orgName) ? 'position_first' : 'name_first';

                    continue;
                }

                if ($this->isHeaderRow($cells)) {
                    $this->columnOrder = $this->detectColumnOrder($cells);
                    if ($this->columnOrder === 'name_first' && $this->isSumOrg($orgName)) {
                        $this->columnOrder = 'position_first';
                    }

                    continue;
                }

                $entry = $this->toEntry($cells);

                if ($entry === null) {
                    continue;
                }

                $sortOrder++;

                $rows[] = $entry + [
                    'org_name' => $orgName !== '' ? $orgName : 'Бусад',
                    'org_order' => max($orgOrder, 1),
                    'sort_order' => $sortOrder,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $joined = mb_strtolower(implode(' ', $cells));

        foreach (['овог', 'албан тушаал', 'гар утас', 'өрөөний утас', 'албан хаагч'] as $needle) {
            if (str_contains($joined, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $cells
     * @return 'name_first'|'position_first'
     */
    private function detectColumnOrder(array $cells): string
    {
        $nameIdx = null;
        $posIdx = null;

        foreach ($cells as $i => $cell) {
            $v = mb_strtolower($cell);
            if ($nameIdx === null && (str_contains($v, 'овог') || str_contains($v, 'албан хаагч') || $v === 'нэр')) {
                $nameIdx = $i;
            }
            if ($posIdx === null && str_contains($v, 'албан тушаал')) {
                $posIdx = $i;
            }
        }

        if ($nameIdx !== null && $posIdx !== null && $posIdx < $nameIdx) {
            return 'position_first';
        }

        return 'name_first';
    }

    private function isSumOrg(string $orgName): bool
    {
        return str_contains(mb_strtolower($orgName), 'сум');
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, string|null>|null
     */
    private function toEntry(array $cells): ?array
    {
        // Эхний нүд зөвхөн дугаар бол хасна.
        if (isset($cells[0]) && preg_match('/^\d+[.)]?$/u', $cells[0])) {
            array_shift($cells);
            $cells = array_values($cells);
        }

        $a = trim($cells[0] ?? '');
        $b = trim($cells[1] ?? '');

        if ($a === '') {
            return null;
        }

        if ($this->columnOrder === 'position_first') {
            $personName = $b !== '' ? $b : $a;
            $position = $b !== '' ? $a : null;
        } else {
            $personName = $a;
            $position = $b !== '' ? $b : null;
        }

        // Аль хэдийн солигдсон мөрийг нэмэлтээр илрүүлнэ.
        if ($position && $this->looksLikePosition($personName) && $this->looksLikePersonName($position)) {
            [$personName, $position] = [$position, $personName];
        }

        return [
            'person_name' => mb_substr($personName, 0, 255),
            'position' => $position ? mb_substr($position, 0, 255) : null,
            'office_phone' => mb_substr(trim($cells[2] ?? ''), 0, 64) ?: null,
            'mobile_phone' => mb_substr(trim($cells[3] ?? ''), 0, 64) ?: null,
        ];
    }

    private function looksLikePersonName(string $value): bool
    {
        $value = trim($value);

        // А.Жаргалбаяр / Н.Бат-Эрдэнэ
        if (preg_match('/^[\p{L}]{1,4}\.\s*[\p{L}\-үөёҮӨЁ]+$/u', $value)) {
            return true;
        }

        return false;
    }

    private function looksLikePosition(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        foreach (['дарга', 'нарийн бичиг', 'эрхлэгч', 'мэргэжилтэн', 'нягтлан', 'багийн', 'зовлон', 'итх', 'здтг'] as $needle) {
            if (str_contains($v, $needle)) {
                return true;
            }
        }

        return false;
    }
}
