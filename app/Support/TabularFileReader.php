<?php

namespace App\Support;

use RuntimeException;

/**
 * Word, Excel, PDF файлаас хүснэгтийн мөрүүдийг нэг хэлбэрээр уншина.
 */
class TabularFileReader
{
    public const EXTENSIONS = ['docx', 'docm', 'xlsx', 'xlsm', 'pdf'];

    public function __construct(
        private readonly DocxTableReader $docx,
        private readonly XlsxTableReader $xlsx,
        private readonly PdfTableReader $pdf,
    ) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'docx', 'docm' => $this->flatten($this->docx->tables($path)),
            'xlsx', 'xlsm' => $this->xlsx->rows($path),
            'pdf' => $this->pdf->rows($path),
            default => throw new RuntimeException('Зөвхөн Word (.docx), Excel (.xlsx), PDF файл дэмжинэ.'),
        };
    }

    /**
     * @param  array<int, array<int, array<int, string>>>  $tables
     * @return array<int, array<int, string>>
     */
    private function flatten(array $tables): array
    {
        $rows = [];

        foreach ($tables as $table) {
            foreach ($table as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
