<?php

/**
 * Сайдын гэрээний Word файлуудыг тайлангийн JSON болгоно.
 * Usage: php scripts/import_contract_docx.php [docx_dir]
 */

declare(strict_types=1);

use App\Support\DocxTableReader;

$baseDir = dirname(__DIR__);
require $baseDir.'/vendor/autoload.php';

$sourceDir = $argv[1] ?? 'C:/Users/IT-PC/Downloads/erp-attachments-20261102163093';
$outDir = $baseDir.'/database/data/reports';

$keys = [
    '1' => 'contracts.ministry_zgheg',
    '2' => 'contracts.deputy_dorjhand',
    '3' => 'contracts.deputy_gankhuyag',
    '4' => 'contracts.ministry_digital',
    '5' => 'contracts.ministry_foreign',
    '6' => 'contracts.ministry_finance',
    '7' => 'contracts.ministry_defense',
    '8' => 'contracts.ministry_legal',
    '9' => 'contracts.ministry_economy',
    '10' => 'contracts.ministry_environment',
    '11' => 'contracts.ministry_mram',
    '12' => 'contracts.ministry_urban',
    '13' => 'contracts.ministry_food',
    '14' => 'contracts.ministry_energy',
    '15' => 'contracts.ministry_road',
    '16' => 'contracts.ministry_gbxn',
    '17' => 'contracts.ministry_education',
    '18' => 'contracts.ministry_culture',
    '19' => 'contracts.ministry_health',
];

$fields = ['no', 'document', 'count', 'goal', 'measure', 'clause', 'progress', 'note'];

/** @var array<int, array{0: string, 1: string}> */
$keywords = [
    ['гүйцэтгэлийншалгуурүзүүлэлт', 'goal'],
    ['төсөларгахэмжээнийнэр', 'measure'],
    ['хэрэгжүүлэхаргахэмжээ', 'measure'],
    ['хэрэгжүүлэхүйлажиллагаа', 'measure'],
    ['шалгуурүзүүлэлт', 'goal'],
    ['суурьүзүүлэлт', 'note'],
    ['суурьтүвшин', 'note'],
    ['зорилтоттүвшин', 'progress'],
    ['хүрэхтүвшин', 'progress'],
    ['хүрсэнтүвшин', 'progress'],
    ['төслийнкод', 'document'],
    ['хариуцахнэгж', 'document'],
    ['хариуцах', 'document'],
    ['санхүүжихдүн', 'count'],
    ['төсөвтөртөг', 'count'],
    ['үйлажиллагаа', 'measure'],
    ['аргахэмжээ', 'measure'],
    ['үзүүлэлт', 'goal'],
    ['биелэлт', 'progress'],
    ['үрдүн', 'progress'],
    ['хэрэгжилт', 'progress'],
    ['үнэлгээ', 'progress'],
    ['төсөв', 'count'],
    ['хугацаа', 'clause'],
    ['эхлэх', 'clause'],
    ['дуусах', 'clause'],
    ['дд', 'no'],
];

function tidy(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/ *\n */u', "\n", $value) ?? $value;

    return trim($value);
}

function norm(string $value): string
{
    $value = mb_strtolower(tidy($value));
    $value = str_replace(['/', '\\', ' ', "\n", '.', ',', ':', ';', '-', '–', '—', '(', ')', '№', '«', '»'], '', $value);

    return $value;
}

function fieldForHeader(string $header, array $keywords): ?string
{
    $raw = tidy($header);
    if ($raw === '№' || $raw === 'Д/д' || $raw === 'Д/Д' || $raw === 'д/д') {
        return 'no';
    }

    $normalized = norm($header);
    if ($normalized === '') {
        return null;
    }

    foreach ($keywords as [$needle, $field]) {
        if (str_contains($normalized, $needle)) {
            return $field;
        }
    }

    if (str_contains($normalized, 'суурь')) {
        return 'note';
    }
    if (str_contains($normalized, 'үзүүл') || str_contains($normalized, 'узуул')) {
        return 'goal';
    }
    if (str_contains($normalized, 'биел') || str_contains($normalized, 'биөл')) {
        return 'progress';
    }
    if (str_contains($normalized, 'хурэх') || str_contains($normalized, 'хүрэх') || str_contains($normalized, 'хүрсэн')) {
        return 'progress';
    }
    if (str_contains($normalized, 'аргахэмж')) {
        return 'measure';
    }

    return null;
}

/**
 * @param  array<int, array<int, string>>  $rows
 * @return array{0: ?int, 1: array<int, string>}
 */
function detectHeader(array $rows, array $keywords): array
{
    $bestIndex = null;
    $bestMap = [];
    $bestScore = 1;

    foreach ($rows as $index => $row) {
        $map = [];
        foreach ($row as $col => $cell) {
            $field = fieldForHeader($cell, $keywords);
            if ($field !== null) {
                $map[$col] = $field;
            }
        }
        $score = count(array_unique($map));
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestIndex = $index;
            $bestMap = $map;
        }
    }

    return [$bestIndex, $bestMap];
}

function blankRow(array $fields): array
{
    return array_fill_keys($fields, '');
}

function appendField(array &$row, string $field, string $value): void
{
    $value = tidy($value);
    if ($value === '') {
        return;
    }
    $row[$field] = $row[$field] === '' ? $value : $row[$field]."\n".$value;
}

/**
 * @param  array<int, string>  $cells
 * @param  array<int, string>  $map
 */
function rowFromCells(array $cells, array $map, array $fields): ?array
{
    $filled = [];
    foreach ($cells as $cell) {
        $cell = tidy($cell);
        if ($cell !== '') {
            $filled[] = $cell;
        }
    }
    if ($filled === []) {
        return null;
    }

    $row = blankRow($fields);
    if (count($filled) === 1 && mb_strlen($filled[0]) > 24) {
        $row['document'] = $filled[0];
        if (preg_match('/^(\d+(?:\.\d+)*)\b/u', $filled[0], $m)) {
            $row['no'] = $m[1];
        }

        return $row;
    }

    $used = [];
    foreach ($cells as $index => $cell) {
        $field = $map[$index] ?? null;
        if ($field === null) {
            appendField($row, 'note', $cell);
            continue;
        }
        if ($field === 'no' && mb_strlen(tidy($cell)) > 24) {
            appendField($row, 'document', $cell);
            continue;
        }
        appendField($row, $field, $cell);
        $used[$field] = true;
    }

    if ($row === blankRow($fields)) {
        return null;
    }

    return $row;
}

/**
 * @param  array<int, string>  $cells
 * @return array<int, string>
 */
function fallbackMap(array $cells): array
{
    $count = count($cells);
    if ($count <= 1) {
        return [0 => 'measure'];
    }
    if ($count === 2) {
        return [0 => 'measure', 1 => 'count'];
    }

    $map = [0 => 'no', 1 => 'measure'];
    if ($count === 3) {
        $map[2] = 'count';

        return $map;
    }

    $tail = ['count', 'goal', 'note', 'progress', 'clause', 'document'];
    for ($i = 2; $i < $count; $i++) {
        $map[$i] = $tail[$i - 2] ?? 'note';
    }

    return $map;
}

function isIndexRow(array $cells): bool
{
    $values = [];
    foreach ($cells as $cell) {
        $cell = tidy($cell);
        if ($cell === '') {
            continue;
        }
        if (! preg_match('/^\d{1,2}$/u', $cell)) {
            return false;
        }
        $values[] = $cell;
    }

    return count($values) >= 3;
}

function isSubheader(array $cells, array $keywords): bool
{
    $filled = 0;
    $matched = 0;
    foreach ($cells as $cell) {
        $cell = tidy($cell);
        if ($cell === '') {
            continue;
        }
        if (mb_strlen($cell) > 28) {
            return false;
        }
        $filled++;
        if (fieldForHeader($cell, $keywords) !== null) {
            $matched++;
        }
    }

    return $filled >= 2 && $matched === $filled;
}

function tablesAreThin(array $rows): bool
{
    if ($rows === []) {
        return true;
    }

    $thin = 0;
    foreach ($rows as $row) {
        $text = '';
        foreach ($row as $key => $value) {
            if ($key !== 'no') {
                $text .= $value;
            }
        }
        if (mb_strlen($text) < 12) {
            $thin++;
        }
    }

    return ($thin / count($rows)) > 0.6;
}

function sameHeader(array $cells, array $header): bool
{
    if ($header === []) {
        return false;
    }

    $left = array_map(static fn ($cell) => norm((string) $cell), $cells);
    $right = array_map(static fn ($cell) => norm((string) $cell), $header);
    $width = max(count($left), count($right));
    $left = array_pad($left, $width, '');
    $right = array_pad($right, $width, '');

    return $left === $right && count(array_filter($left)) >= 2;
}

/**
 * @param  array<int, array<int, string>>  $tables
 * @return array<int, array<string, string>>
 */
function rowsFromTables(array $tables, array $keywords, array $fields): array
{
    $merged = [];

    foreach ($tables as $table) {
        $flat = [];
        foreach ($table as $row) {
            $flat[] = array_map(static fn ($cell) => tidy((string) $cell), $row);
        }

        [$headerIndex, $map] = detectHeader($flat, $keywords);
        $header = $headerIndex === null ? [] : $flat[$headerIndex];
        if ($map === []) {
            $map = fallbackMap($flat[0] ?? []);
            $headerIndex = null;
            $header = [];
        }

        $body = $headerIndex === null ? $flat : array_slice($flat, $headerIndex + 1);

        foreach ($body as $cells) {
            $first = tidy($cells[0] ?? '');
            $filled = 0;
            foreach ($cells as $cell) {
                if (tidy($cell) !== '') {
                    $filled++;
                }
            }
            if ($filled === 0 || sameHeader($cells, $header) || isIndexRow($cells) || isSubheader($cells, $keywords)) {
                continue;
            }

            $continues = $first === '' && $filled <= 2 && $merged !== [];
            if ($continues) {
                $previous = array_pop($merged);
                foreach ($cells as $index => $cell) {
                    $field = $map[$index] ?? 'note';
                    if ($field === 'no') {
                        $field = 'document';
                    }
                    appendField($previous, $field, $cell);
                }
                $merged[] = $previous;
                continue;
            }

            $row = rowFromCells($cells, $map, $fields);
            if ($row !== null) {
                $merged[] = $row;
            }
        }
    }

    return $merged;
}

function paragraphs(string $path): array
{
    $zip = new ZipArchive;
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        return [];
    }
    $xml = $zip->getFromName('word/document.xml') ?: '';
    $zip->close();

    $dom = new DOMDocument;
    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $out = [];
    foreach ($xpath->query('//w:p') as $paragraph) {
        $text = '';
        foreach ($xpath->query('.//w:t', $paragraph) as $node) {
            $text .= $node->textContent;
        }
        $text = tidy($text);
        if ($text !== '') {
            $out[] = $text;
        }
    }

    return $out;
}

/**
 * @param  array<int, string>  $lines
 * @return array<int, array<string, string>>
 */
function rowsFromParagraphs(array $lines, array $fields): array
{
    $rows = [];
    $previous = null;

    foreach ($lines as $line) {
        $normalized = norm($line);
        if ($normalized === '' || $normalized === 'дорноговьаймаг' || mb_strlen($line) < 8) {
            continue;
        }
        if ($previous !== null && $previous === $line) {
            continue;
        }
        $previous = $line;

        $row = blankRow($fields);
        if (preg_match('/^(Нэг|Хоёр|Гурав|Дөрөв|Тав|Зургаа|Долоо|Найм|Ес|Арав)\b/u', $line)) {
            $row['document'] = $line;
        } elseif (preg_match('/^(\d+(?:\.\d+)+)\.?\s*(.*)$/u', $line, $m) && mb_strlen($m[2]) > 0) {
            $row['no'] = $m[1];
            $row['measure'] = tidy($m[2]);
        } else {
            $row['measure'] = $line;
        }
        $rows[] = $row;
    }

    return $rows;
}

$reader = new DocxTableReader;
$files = glob($sourceDir.'/*.docx') ?: [];
sort($files, SORT_NATURAL);

$written = [];
foreach ($files as $path) {
    $name = basename($path);
    if (! preg_match('/^(\d+)/u', $name, $m) || ! isset($keys[$m[1]])) {
        fwrite(STDERR, "SKIP $name\n");
        continue;
    }

    $key = $keys[$m[1]];
    $tables = $reader->tables($path);
    $rows = $tables !== []
        ? rowsFromTables($tables, $keywords, $fields)
        : [];
    $fromParagraphs = $tables === [];
    if (tablesAreThin($rows)) {
        $rows = rowsFromParagraphs(paragraphs($path), $fields);
        $fromParagraphs = true;
    }

    if ($rows === []) {
        fwrite(STDERR, "EMPTY $name\n");
        continue;
    }

    $payload = [
        'report_key' => $key,
        'source_file' => $name,
        'sheet' => null,
        'imported_at' => date('c'),
        'row_count' => count($rows),
        'rows' => array_values($rows),
    ];

    $target = $outDir.'/'.$key.'.json';
    file_put_contents($target, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $written[$key] = count($rows);
    echo $key.' '.$name.' rows='.count($rows).($fromParagraphs ? ' paragraphs' : ' tables='.count($tables))."\n";
}

$missing = array_diff($keys, array_keys($written));
if ($missing !== []) {
    fwrite(STDERR, 'MISSING '.implode(', ', $missing)."\n");
    exit(1);
}

echo 'OK '.count($written)." files\n";
