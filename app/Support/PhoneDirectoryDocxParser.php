<?php

namespace App\Support;

/**
 * Word (.docx) файлын хүснэгтээс утасны жагсаалтыг уншина.
 *
 * Хүлээгдэж буй толгой: № | Овог нэр | Албан тушаал | Ажлын өрөөний утас | Гар утас
 * Ганц нүдтэй (нийлүүлсэн) мөрийг байгууллагын нэр — бүлгийн гарчиг гэж үзнэ.
 */
class PhoneDirectoryDocxParser
{
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

                    continue;
                }

                if ($this->isHeaderRow($cells)) {
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

        foreach (['овог', 'албан тушаал', 'гар утас', 'өрөөний утас'] as $needle) {
            if (str_contains($joined, $needle)) {
                return true;
            }
        }

        return false;
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

        $name = trim($cells[0] ?? '');

        if ($name === '') {
            return null;
        }

        return [
            'person_name' => mb_substr($name, 0, 255),
            'position' => mb_substr(trim($cells[1] ?? ''), 0, 255) ?: null,
            'office_phone' => mb_substr(trim($cells[2] ?? ''), 0, 64) ?: null,
            'mobile_phone' => mb_substr(trim($cells[3] ?? ''), 0, 64) ?: null,
        ];
    }
}
