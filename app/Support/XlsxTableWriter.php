<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Энгийн нэг хуудастай Excel (.xlsx) файл бичнэ.
 *
 * Гадны сан ашиглахгүй — SpreadsheetML-ийн хамгийн бага бүтцийг өөрөө үүсгэнэ.
 */
class XlsxTableWriter
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function write(string $path, string $title, array $headings, array $rows): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel файл үүсгэж чадсангүй.');
        }

        $sheetRows = array_merge([$headings], array_map(
            fn (array $row) => array_map(fn ($v) => (string) ($v ?? ''), $row),
            $rows,
        ));

        // Гарчиг — эхний мөрөнд нэгтгэнэ.
        $colCount = max(1, count($headings));
        $lastCol = $this->columnLetter($colCount - 1);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStrings($title, $sheetRows));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($title, $sheetRows, $lastCol));
        $zip->close();
    }

    /**
     * @param  array<int, array<int, string>>  $sheetRows
     */
    private function sharedStrings(string $title, array $sheetRows): string
    {
        $strings = [$title];

        foreach ($sheetRows as $row) {
            foreach ($row as $cell) {
                $strings[] = $cell;
            }
        }

        $this->stringIndex = [];
        $unique = [];
        $xml = '';

        foreach ($strings as $text) {
            if (isset($this->stringIndex[$text])) {
                continue;
            }

            $this->stringIndex[$text] = count($unique);
            $unique[] = $text;
            $xml .= '<si><t xml:space="preserve">'.$this->escape($text).'</t></si>';
        }

        $count = count($unique);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            ." count=\"{$count}\" uniqueCount=\"{$count}\">{$xml}</sst>";
    }

    /** @var array<string, int> */
    private array $stringIndex = [];

    /**
     * @param  array<int, array<int, string>>  $sheetRows
     */
    private function sheet(string $title, array $sheetRows, string $lastCol): string
    {
        $rowXml = '';
        $r = 1;

        // Гарчиг
        $titleIdx = $this->stringIndex[$title] ?? 0;
        $rowXml .= "<row r=\"{$r}\">"
            ."<c r=\"A{$r}\" t=\"s\" s=\"1\"><v>{$titleIdx}</v></c>"
            .'</row>';
        $r++;

        foreach ($sheetRows as $i => $cells) {
            $cellsXml = '';
            $style = $i === 0 ? '2' : '0';

            foreach ($cells as $c => $text) {
                $ref = $this->columnLetter($c).$r;
                $idx = $this->stringIndex[$text] ?? 0;
                $cellsXml .= "<c r=\"{$ref}\" t=\"s\" s=\"{$style}\"><v>{$idx}</v></c>";
            }

            $rowXml .= "<row r=\"{$r}\">{$cellsXml}</row>";
            $r++;
        }

        $end = $r - 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<dimension ref=\"A1:{$lastCol}{$end}\"/>"
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            ."<sheetData>{$rowXml}</sheetData>"
            .'</worksheet>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index + 1;

        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26);
        }

        return $letter;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Бүртгэл" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="14"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCE6F1"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border/><border>'
            .'<left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/>'
            .'</border></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf fontId="0" fillId="0" borderId="1" xfId="0"/>'
            .'<xf fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
