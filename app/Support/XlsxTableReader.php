<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Excel (.xlsx) файлын эхний хуудсыг мөр/нүдний массив болгож уншина.
 */
class XlsxTableReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Excel файлыг нээж чадсангүй. .xlsx хэлбэрээр хадгалж дахин оруулна уу.');
        }

        $shared = $this->sharedStrings($zip);
        $sheet = $this->firstSheetXml($zip);
        $zip->close();

        if ($sheet === null) {
            throw new RuntimeException('Excel файлаас хуудас олдсонгүй.');
        }

        return $this->parseSheet($sheet, $shared);
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::NS);

        $strings = [];

        foreach ($xpath->query('//s:si') as $si) {
            $parts = [];

            foreach ($xpath->query('.//s:t', $si) as $t) {
                $parts[] = $t->textContent;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function firstSheetXml(ZipArchive $zip): ?string
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^xl/worksheets/sheet\d*\.xml$#', $name)) {
                $names[] = $name;
            }
        }

        if (! $names) {
            return null;
        }

        natsort($names);
        $xml = $zip->getFromName((string) reset($names));

        return $xml === false ? null : $xml;
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string>>
     */
    private function parseSheet(string $xml, array $shared): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::NS);

        $rows = [];

        foreach ($xpath->query('//s:sheetData/s:row') as $row) {
            $cells = [];

            foreach ($xpath->query('./s:c', $row) as $c) {
                /** @var DOMElement $c */
                $index = $this->columnIndex((string) $c->getAttribute('r'), count($cells));
                $cells[$index] = $this->cellValue($xpath, $c, $shared);
            }

            if (! $cells) {
                $rows[] = [];

                continue;
            }

            // Дунд нь хоосон үлдсэн баганыг нөхнө.
            $max = max(array_keys($cells));
            $line = [];

            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $shared
     */
    private function cellValue(DOMXPath $xpath, DOMElement $c, array $shared): string
    {
        $type = (string) $c->getAttribute('t');

        if ($type === 'inlineStr') {
            $parts = [];

            foreach ($xpath->query('.//s:t', $c) as $t) {
                $parts[] = $t->textContent;
            }

            return trim(implode('', $parts));
        }

        $value = $xpath->query('./s:v', $c)->item(0);

        if (! $value) {
            return '';
        }

        $raw = $value->textContent;

        if ($type === 's') {
            return trim($shared[(int) $raw] ?? '');
        }

        return trim($raw);
    }

    /**
     * "B7" → 1. Хаяг байхгүй бол дарааллаар нь авна.
     */
    private function columnIndex(string $reference, int $fallback): int
    {
        if (! preg_match('/^([A-Z]+)/', $reference, $m)) {
            return $fallback;
        }

        $index = 0;

        foreach (str_split($m[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
