<?php

namespace App\Support;

/**
 * Байгууллагын албан хаагчдын утасны жагсаалтыг Word (.docx) файлаас уншина.
 *
 * Хүлээгдэж буй толгой:
 * № | Байгууллага | Нэгж | Албан тушаал | Овог | Нэр | Өрөө | Ажлын утас | Гар утас | И-мэйл хаяг
 */
class OrgEmployeePhoneDocxParser
{
    public function __construct(private readonly DocxTableReader $reader) {}

    /**
     * @return array<int, array<string, string|null>>
     */
    public function parse(string $path): array
    {
        $tables = $this->reader->tables($path);
        $rows = [];

        foreach ($tables as $table) {
            foreach ($table as $row) {
                $rows[] = $row;
            }
        }

        return $this->fromRows($rows);
    }

    /**
     * Word/Excel/PDF-ээс уншсан мөрүүдийг бүртгэл болгоно.
     *
     * @param  array<int, array<int, string>>  $input
     * @return array<int, array<string, string|null>>
     */
    public function fromRows(array $input): array
    {
        $rows = [];
        $organization = '';
        $sortOrder = 0;

        foreach ($input as $cells) {
            {
                $cells = array_map(
                    fn (string $c) => trim((string) preg_replace('/\s+/u', ' ', $c)),
                    $cells
                );

                $filled = array_values(array_filter($cells, fn (string $c) => $c !== ''));

                if (! $filled || $this->isHeaderRow($cells)) {
                    continue;
                }

                // Нийлүүлсэн ганц нүдтэй мөр — байгууллагын нэр.
                if (count($filled) === 1) {
                    $organization = $filled[0];

                    continue;
                }

                $entry = $this->toEntry($cells, $organization);

                if ($entry === null) {
                    continue;
                }

                $sortOrder++;
                $rows[] = $entry + ['sort_order' => $sortOrder];
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

        foreach (['байгууллага', 'албан тушаал', 'и-мэйл', 'ажлын утас'] as $needle) {
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
    private function toEntry(array $cells, string $organization): ?array
    {
        if (isset($cells[0]) && preg_match('/^\d+[.)]?$/u', $cells[0])) {
            array_shift($cells);
            $cells = array_values($cells);
        }

        $clean = fn (?string $v, int $limit) => ($v = trim((string) $v)) === ''
            ? null
            : mb_substr($v, 0, $limit);

        $org = $clean($cells[0] ?? '', 255) ?? ($organization !== '' ? $organization : null);
        $lastName = $clean($cells[3] ?? '', 255);
        $firstName = $clean($cells[4] ?? '', 255);

        // Овог/нэр хоёулаа хоосон бол мөр биш.
        if ($lastName === null && $firstName === null) {
            return null;
        }

        return [
            'organization' => $org ?? 'Бусад',
            'unit' => $clean($cells[1] ?? '', 255),
            'position' => $clean($cells[2] ?? '', 255),
            'last_name' => $lastName ?? '—',
            'first_name' => $firstName ?? '—',
            'room' => $clean($cells[5] ?? '', 64),
            'work_phone' => $clean($cells[6] ?? '', 64),
            'mobile_phone' => $clean($cells[7] ?? '', 64),
            'email' => $this->email($clean($cells[8] ?? '', 255)),
        ];
    }

    private function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }
}
