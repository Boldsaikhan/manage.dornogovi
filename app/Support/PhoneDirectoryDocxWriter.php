<?php

namespace App\Support;

/**
 * Утасны жагсаалтыг Word (.docx) файл болгож бичнэ.
 */
class PhoneDirectoryDocxWriter
{
    private const COLUMN_WIDTHS = [700, 3200, 3400, 1700, 1700]; // twip

    private const HEADINGS = ['№', 'Овог нэр', 'Албан тушаал', 'Ажлын өрөөний утас', 'Гар утас'];

    /** АЗДТГ-н албан хаагчдын дэлгэрэнгүй жагсаалт. */
    private const STAFF_HEADINGS = [
        '№', 'Байгууллага', 'Нэгж', 'Албан тушаал', 'Овог', 'Нэр',
        'Өрөө', 'Ажлын утас', 'Гар утас', 'И-мэйл хаяг',
    ];

    private const STAFF_WIDTHS = [500, 2200, 1500, 2000, 1300, 1300, 700, 1100, 1100, 2000];

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

    /**
     * АЗДТГ-н албан хаагчдын жагсаалт — хэвтээ хуудсаар.
     *
     * @param  array<int, array<string, string|null>>  $staff
     */
    public function writeStaff(array $staff, string $path, string $title = 'АЗДТГ-н албан хаагчдын утасны жагсаалт'): void
    {
        $rows = [];

        foreach (array_values($staff) as $index => $row) {
            $rows[] = ['type' => 'data', 'cells' => [
                (string) ($index + 1),
                (string) ($row['organization'] ?? ''),
                (string) ($row['unit'] ?? ''),
                (string) ($row['position'] ?? ''),
                (string) ($row['last_name'] ?? ''),
                (string) ($row['first_name'] ?? ''),
                (string) ($row['room'] ?? ''),
                (string) ($row['work_phone'] ?? ''),
                (string) ($row['mobile_phone'] ?? ''),
                (string) ($row['email'] ?? ''),
            ]];
        }

        $this->writer->write(
            $path,
            $title,
            self::STAFF_HEADINGS,
            self::STAFF_WIDTHS,
            $rows,
            centerColumns: [0, 6, 7, 8],
            landscape: true,
        );
    }
}
