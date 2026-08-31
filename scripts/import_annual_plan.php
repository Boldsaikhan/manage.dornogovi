<?php

/**
 * Import АЖХТ xlsx → local_policy.annual_plan JSON
 * Usage: php scripts/import_annual_plan.php [xlsx_path]
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$source = $argv[1] ?? 'C:/Users/IT-PC/Desktop/хяналт шинжилгээ/АЖХТ-2026-ЭЦЭС.xlsx';
$output = $baseDir.'/database/data/reports/local_policy.annual_plan.json';

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
        $cells = array_fill(0, 30, '');
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
    return $row[columnIndex($col)] ?? '';
}

function isSectionHeader(array $row): bool
{
    $no = cell($row, 'A');
    $measure = cell($row, 'F');

    if ($no !== '' && is_numeric($no)) {
        return false;
    }

    if ($no !== '' && ! is_numeric($no) && $measure === '') {
        return true;
    }

    return false;
}

function isSummaryRow(array $row): bool
{
    $measure = mb_strtolower(cell($row, 'F'));

    return in_array($measure, ['arga hemjee', 'tusul', 'undsen uil ajillagaa', 'arga hemjee 110', 'tusul 36', 'undsen uil ajillagaa 58'], true);
}

function isDataRow(array $row): bool
{
    if (isSectionHeader($row) || isSummaryRow($row)) {
        return false;
    }

    $no = cell($row, 'A');
    $policy = cell($row, 'D');
    $measure = cell($row, 'F');
    $indicator = cell($row, 'G');

    if ($no !== '' && is_numeric($no)) {
        return $measure !== '' || $policy !== '' || $indicator !== '';
    }

    return $measure !== '' && ($policy !== '' || $indicator !== '');
}

function percentValue(string $baseline, string $target): string
{
    if (! is_numeric($baseline) || ! is_numeric($target) || (float) $target == 0.0) {
        return '';
    }

    return (string) round(((float) $baseline / (float) $target) * 100, 1);
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

$sheetName = '2026';
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
    if ($sheet->getAttribute('name') === $sheetName) {
        $sheetPath = 'xl/'.$relIds[$sheet->getAttribute('r:id')];
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
$currentTez = '';
$currentProgram = '';

foreach ($rawRows as $rowNum => $row) {
    if ($rowNum <= 12) {
        continue;
    }

    if (! isDataRow($row)) {
        continue;
    }

    $tez = cell($row, 'B');
    $program = cell($row, 'C');

    if ($tez !== '') {
        $currentTez = $tez;
    }
    if ($program !== '') {
        $currentProgram = $program;
    }

    $goal = $currentProgram !== '' ? $currentProgram : $currentTez;
    $baseline = cell($row, 'J');
    $target = cell($row, 'K');

    $items[] = [
        'policy_unit' => cell($row, 'D'),
        'year' => cell($row, 'I'),
        'clause' => cell($row, 'E'),
        'goal' => $goal,
        'measure' => cell($row, 'F'),
        'indicator' => cell($row, 'G'),
        'unit' => cell($row, 'H'),
        'baseline' => $baseline,
        'target' => $target,
        'progress' => $baseline,
        'percent' => percentValue($baseline, $target),
        'department' => cell($row, 'N'),
    ];
}

$payload = [
    'report_key' => 'local_policy.annual_plan',
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
