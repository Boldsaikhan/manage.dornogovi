<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Word (.docx) файлын хүснэгтүүдийг мөр/нүдний энгийн массив болгож уншина.
 */
class DocxTableReader
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return array<int, array<int, array<int, string>>> Хүснэгт → мөр → нүдний текст
     */
    public function tables(string $path): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($this->documentXml($path), LIBXML_NOENT | LIBXML_NONET);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);

        $tables = [];

        foreach ($xpath->query('//w:tbl') as $table) {
            $rows = [];

            foreach ($xpath->query('.//w:tr', $table) as $tr) {
                $cells = [];

                foreach ($xpath->query('./w:tc', $tr) as $tc) {
                    $cells[] = $this->cellText($xpath, $tc);
                }

                if ($cells) {
                    $rows[] = $cells;
                }
            }

            if ($rows) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive;

        // RDONLY — эс бөгөөс буруу файлыг хаахад ZipArchive эх файлыг устгаж болно.
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Word файлыг нээж чадсангүй. .docx хэлбэрээр хадгалж дахин оруулна уу.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Файлын агуулга уншигдсангүй. .docx хэлбэрээр хадгалж дахин оруулна уу.');
        }

        return $xml;
    }

    private function cellText(DOMXPath $xpath, DOMElement $tc): string
    {
        $parts = [];

        foreach ($xpath->query('.//w:p', $tc) as $p) {
            $line = [];

            foreach ($xpath->query('.//w:t', $p) as $t) {
                $line[] = $t->textContent;
            }

            $line = trim((string) preg_replace('/[ \t]+/u', ' ', implode('', $line)));

            if ($line !== '') {
                $parts[] = $line;
            }
        }

        return trim(implode("\n", $parts));
    }
}
