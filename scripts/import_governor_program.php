<?php

/**
 * Import АЗДҮАХ xlsx → local_policy.governor_program JSON
 * Usage: php scripts/import_governor_program.php [xlsx_path]
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$source = $argv[1] ?? 'C:/Users/IT-PC/Desktop/хяналт шинжилгээ/АЗДҮАХ-2024-2028-2026-оны-хагас-жил-ТАЙЛАН-хуваарилалт.xlsx';
$output = $baseDir.'/storage/app/reports/local_policy.governor_program.json';

function readSharedStrings(ZipArchive $zip): array
{
    $shared = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if (! $xml) {
        return $shared;
    }
    $doc = new DOMDocument;
    $doc->loadXML($xml);
    foreach ($doc->getElementsByTagName('si') as $si) {
        $parts = [];
        foreach ($si->getElementsByTagName('t') as $t) {
            $parts[] = $t->textContent;
        }
        $shared[] = html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    return $shared;
}

function columnIndex(string $letters): int
{
    $index = 0;
    foreach (str_split($letters) as $char) {
        $index = $index * 26 + (ord($char) - 64);
    }

    return $index - 1;
}

function readSheetRows(ZipArchive $zip, string $sheetPath, array $shared): array
{
    $xml = $zip->getFromName($sheetPath);
    if (! $xml) {
        return [];
    }

    $doc = new DOMDocument;
    $doc->loadXML($xml);
    $rows = [];

    foreach ($doc->getElementsByTagName('row') as $row) {
        $rowNum = (int) $row->getAttribute('r');
        $cells = array_fill(0, 22, '');
        foreach ($row->getElementsByTagName('c') as $cell) {
            if (! preg_match('/^([A-Z]+)(\d+)$/', $cell->getAttribute('r'), $m)) {
                continue;
            }
            $col = columnIndex($m[1]);
            $type = $cell->getAttribute('t');
            $value = $cell->getElementsByTagName('v')->item(0)?->textContent ?? '';
            if ($type === 's' && isset($shared[(int) $value])) {
                $value = $shared[(int) $value];
            }
            $cells[$col] = trim((string) $value);
        }
        $rows[$rowNum] = $cells;
    }

    ksort($rows);

    return $rows;
}

function cell(array $row, string $col): string
{
    $index = columnIndex($col);

    return $row[$index] ?? '';
}

function isSectionHeader(array $row): bool
{
    $no = cell($row, 'A');
    $activity = cell($row, 'B');

    if ($no !== '' && is_numeric($no)) {
        return false;
    }

    if ($activity === '') {
        return true;
    }

    if (preg_match('/^\d+(\.\d+)+\.?\s/u', $activity) && cell($row, 'C') === '' && cell($row, 'D') === '') {
        return true;
    }

    if (mb_stripos($activity, 'НЭГДСЭН') !== false || mb_stripos($activity, 'БОДЛОГО') !== false) {
        return true;
    }

    return false;
}

function isDataRow(array $row): bool
{
    if (isSectionHeader($row)) {
        return false;
    }

    $no = cell($row, 'A');
    $activity = cell($row, 'B');
    $measure = cell($row, 'C');

    if ($no !== '' && is_numeric($no)) {
        return $activity !== '' || $measure !== '';
    }

    return $measure !== '' && $activity === '';
}

if (! file_exists($source)) {
    fwrite(STDERR, "File not found: {$source}\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($source) !== true) {
    fwrite(STDERR, "Cannot open xlsx\n");
    exit(1);
}

$shared = readSharedStrings($zip);

$sheetName = 'ҮАТ';
$workbookXml = $zip->getFromName('xl/workbook.xml');
$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$wb = new DOMDocument;
$wb->loadXML($workbookXml);
$rels = new DOMDocument;
$rels->loadXML($relsXml);
$relIds = [];
foreach ($rels->getElementsByTagName('Relationship') as $rel) {
    $relIds[$rel->getAttribute('Id')] = ltrim(str_replace('../', '', $rel->getAttribute('Target')), '/');
}

$sheetPath = null;
foreach ($wb->getElementsByTagName('sheet') as $sheet) {
    $name = $sheet->getAttribute('name');
    if ($name === $sheetName) {
        $rid = $sheet->getAttribute('r:id');
        $sheetPath = 'xl/'.$relIds[$rid];
        break;
    }
}

if (! $sheetPath) {
    fwrite(STDERR, "{$sheetName} sheet not found\n");
    exit(1);
}

$rawRows = readSheetRows($zip, $sheetPath, $shared);
$zip->close();

$items = [];
$currentNo = null;

foreach ($rawRows as $rowNum => $row) {
    if ($rowNum <= 4) {
        continue;
    }

    if (! isDataRow($row)) {
        continue;
    }

    $no = cell($row, 'A');
    if ($no !== '' && is_numeric($no)) {
        $currentNo = $no;
    }

    $items[] = [
        'no' => $currentNo ?? '',
        'activity' => cell($row, 'B'),
        'measure' => cell($row, 'C'),
        'period' => cell($row, 'D'),
        'source' => cell($row, 'E'),
        'budget' => cell($row, 'F'),
        'indicator' => cell($row, 'G'),
        'unit' => cell($row, 'H'),
        'baseline' => cell($row, 'J'),
        'target' => cell($row, 'N'),
        'progress' => cell($row, 'P') ?: cell($row, 'O'),
        'percent' => cell($row, 'Q'),
        'frequency' => cell($row, 'R'),
        'report_to' => cell($row, 'S'),
        'department' => cell($row, 'S'),
        'agency' => cell($row, 'T'),
    ];
}

$payload = [
    'report_key' => 'local_policy.governor_program',
    'source_file' => basename($source),
    'sheet' => $sheetName,
    'imported_at' => date('c'),
    'row_count' => count($items),
    'rows' => $items,
];

if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
}

file_put_contents($output, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo 'Imported '.count($items)." rows → {$output}\n";
