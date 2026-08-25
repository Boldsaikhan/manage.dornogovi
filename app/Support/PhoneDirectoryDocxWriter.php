<?php

namespace App\Support;

/**
 * Утасны жагсаалтыг Word (.docx) файл болгож бичнэ.
 */
class PhoneDirectoryDocxWriter
{
    private const COLUMN_WIDTHS = [700, 3200, 3400, 1700, 1700]; // twip

    private const HEADINGS = ['№', 'Овог нэр', 'Албан тушаал', 'Ажлын өрөөний утас', 'Гар утас'];


    public function __construct(private readonly DocxTableWriter $writer) {}

    /**
     * @param  array<int, array{org_name: string, rows: array<int, array<string, string|null>>}>  $groups
     */
    public function write(array $groups, string $path, string $title = 'Утасны жагсаалт'): void
    {
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = ['type' => 'group', 'text' => (string) ($group['org_name'] ?? '')];

            foreach (array_values($group['rows'] ?? []) as $index => $row) {
                $rows[] = ['type' => 'data', 'cells' => [
                    (string) ($index + 1),
                    (string) ($row['person_name'] ?? ''),
                    (string) ($row['position'] ?? ''),
                    (string) ($row['office_phone'] ?? ''),
                    (string) ($row['mobile_phone'] ?? ''),
                ]];
            }
        }

        $this->writer->write(
            $path,
            $title,
            self::HEADINGS,
            self::COLUMN_WIDTHS,
            $rows,
            centerColumns: [0, 3, 4],
        );
    }

}
