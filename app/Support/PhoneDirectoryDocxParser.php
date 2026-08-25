<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Word (.docx) файлын хүснэгтээс утасны жагсаалтыг уншина.
 *
 * Хүлээгдэж буй толгой: № | Овог нэр | Албан тушаал | Ажлын өрөөний утас | Гар утас
 * Ганц нүдтэй (нийлүүлсэн) мөрийг байгууллагын нэр — бүлгийн гарчиг гэж үзнэ.
 */
class PhoneDirectoryDocxParser
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        $xml = $this->documentXml($path);

        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);

        $rows = [];
        $orgName = '';
        $orgOrder = 0;
        $sortOrder = 0;

        foreach ($xpath->query('//w:tbl') as $table) {
            foreach ($xpath->query('.//w:tr', $table) as $tr) {
                $cells = [];
                foreach ($xpath->query('./w:tc', $tr) as $tc) {
                    $cells[] = $this->cellText($xpath, $tc);
                }

                $filled = array_values(array_filter($cells, fn (string $c) => $c !== ''));

                if (! $filled) {
                    continue;
                }

                // Нийлүүлсэн ганц утгатай мөр — байгууллагын нэр.
                if (count($filled) === 1) {
                    $orgName = $filled[0];
                    $orgOrder++;
                    $sortOrder = 0;

                    continue;
                }

                if ($this->isHeaderRow($cells)) {
                    continue;
                }

                $entry = $this->toEntry($cells);

                if ($entry === null) {
                    continue;
                }

                $sortOrder++;

                $rows[] = $entry + [
                    'org_name' => $orgName !== '' ? $orgName : 'Бусад',
                    'org_order' => max($orgOrder, 1),
                    'sort_order' => $sortOrder,
                ];
            }
        }

        return $rows;
    }

    private function documentXml(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
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

        foreach ($xpath->query('.//w:t', $tc) as $t) {
            $parts[] = $t->textContent;
        }

        $text = preg_replace('/\s+/u', ' ', implode('', $parts));

        return trim((string) $text);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $joined = mb_strtolower(implode(' ', $cells));

        foreach (['овог', 'албан тушаал', 'гар утас', 'өрөөний утас'] as $needle) {
            if (str_contains($joined, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, string>|null
     */
    private function toEntry(array $cells): ?array
    {
        // Эхний нүд зөвхөн дугаар бол хасна.
        if (isset($cells[0]) && preg_match('/^\d+[.)]?$/u', $cells[0])) {
            array_shift($cells);
        }

        $name = trim($cells[0] ?? '');

        if ($name === '') {
            return null;
        }

        return [
            'person_name' => mb_substr($name, 0, 255),
            'position' => mb_substr(trim($cells[1] ?? ''), 0, 255) ?: null,
            'office_phone' => mb_substr(trim($cells[2] ?? ''), 0, 64) ?: null,
            'mobile_phone' => mb_substr(trim($cells[3] ?? ''), 0, 64) ?: null,
        ];
    }
}
