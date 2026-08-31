<?php

/**
 * Import ИТХТ xlsx → local_policy.council_decision JSON
 * Usage: php scripts/import_council_decision.php [xlsx_path]
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$source = $argv[1] ?? 'C:/Users/IT-PC/Desktop/хяналт шинжилгээ/ИТХТ-ын хэрэгжилт_2026_.xlsx';
$output = $baseDir.'/database/data/reports/local_policy.council_decision.json';

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
        $cells = array_fill(0, 12, '');
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

function isDataRow(array $row): bool
{
    $clauseNo = cell($row, 'B');
    $clauseText = cell($row, 'D');
    $decisionTitle = cell($row, 'C');

    if ($clauseNo === '' && $clauseText === '' && $decisionTitle === '') {
        return false;
    }

    if ($clauseNo !== '' && ! is_numeric($clauseNo)) {
        return false;
    }

    return $clauseText !== '' || $decisionTitle !== '';
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

$sheetName = '2025-2026 оны тогтоол';
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
$currentDecisionNo = '';
$currentDecisionTitle = '';

foreach ($rawRows as $rowNum => $row) {
    if ($rowNum <= 2) {
        continue;
    }

    if (! isDataRow($row)) {
        continue;
    }

    $decisionNo = cell($row, 'A');
    if ($decisionNo !== '' && is_numeric($decisionNo)) {
        $currentDecisionNo = $decisionNo;
    }

    $decisionTitle = cell($row, 'C');
    if ($decisionTitle !== '') {
        $currentDecisionTitle = $decisionTitle;
    }

    $items[] = [
        'decision_no' => $currentDecisionNo,
        'clause_no' => cell($row, 'B'),
        'decision_title' => $currentDecisionTitle,
        'clause_text' => cell($row, 'D'),
        'half_year' => cell($row, 'E'),
        'evaluation' => cell($row, 'F'),
        'note' => cell($row, 'G'),
        'department' => cell($row, 'H'),
    ];
}

$payload = [
    'report_key' => 'local_policy.council_decision',
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
