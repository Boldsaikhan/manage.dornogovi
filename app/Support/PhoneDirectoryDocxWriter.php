<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Утасны жагсаалтыг Word (.docx) файл болгож бичнэ.
 *
 * Гадны сан ашиглахгүй — OOXML-ийн хамгийн бага бүтцийг өөрөө үүсгэнэ.
 */
class PhoneDirectoryDocxWriter
{
    private const COLUMN_WIDTHS = [700, 3200, 3400, 1700, 1700]; // twip

    private const HEADINGS = ['№', 'Овог нэр', 'Албан тушаал', 'Ажлын өрөөний утас', 'Гар утас'];

    /**
     * @param  array<int, array{org_name: string, rows: array<int, array<string, string|null>>}>  $groups
     */
    public function write(array $groups, string $path, string $title = 'Утасны жагсаалт'): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Word файл үүсгэж чадсангүй.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/document.xml', $this->document($groups, $title));
        $zip->close();
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  array<int, array{org_name: string, rows: array<int, array<string, string|null>>}>  $groups
     */
    private function document(array $groups, string $title): string
    {
        $body = $this->heading($title);
        $body .= '<w:tbl>'.$this->tableProperties().$this->headerRow();

        foreach ($groups as $group) {
            $body .= $this->groupRow((string) ($group['org_name'] ?? ''));

            foreach (array_values($group['rows'] ?? []) as $index => $row) {
                $body .= $this->dataRow($index + 1, $row);
            }
        }

        $body .= '</w:tbl>'.$this->sectionProperties();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            ."<w:body>{$body}</w:body></w:document>";
    }

    private function heading(string $title): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="200"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr>'
            .'<w:t xml:space="preserve">'.$this->escape($title).'</w:t></w:r></w:p>';
    }

    private function tableProperties(): string
    {
        $borders = '';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $borders .= "<w:{$edge} w:val=\"single\" w:sz=\"6\" w:space=\"0\" w:color=\"999999\"/>";
        }

        return '<w:tblPr><w:tblW w:w="0" w:type="auto"/>'
            ."<w:tblBorders>{$borders}</w:tblBorders>"
            .'<w:tblLayout w:type="fixed"/></w:tblPr>';
    }

    private function headerRow(): string
    {
        $cells = '';

        foreach (self::HEADINGS as $i => $label) {
            $cells .= $this->cell($label, self::COLUMN_WIDTHS[$i], bold: true, align: 'center', shade: 'DCE6F1');
        }

        return '<w:tr><w:trPr><w:tblHeader/></w:trPr>'.$cells.'</w:tr>';
    }

    private function groupRow(string $orgName): string
    {
        $width = array_sum(self::COLUMN_WIDTHS);

        $cell = '<w:tc><w:tcPr>'
            ."<w:tcW w:w=\"{$width}\" w:type=\"dxa\"/>"
            .'<w:gridSpan w:val="'.count(self::HEADINGS).'"/>'
            .'<w:shd w:val="clear" w:color="auto" w:fill="F2F2F2"/>'
            .'</w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:i/></w:rPr><w:t xml:space="preserve">'.$this->escape($orgName).'</w:t></w:r>'
            .'</w:p></w:tc>';

        return "<w:tr>{$cell}</w:tr>";
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function dataRow(int $no, array $row): string
    {
        $values = [
            (string) $no,
            (string) ($row['person_name'] ?? ''),
            (string) ($row['position'] ?? ''),
            (string) ($row['office_phone'] ?? ''),
            (string) ($row['mobile_phone'] ?? ''),
        ];

        $cells = '';

        foreach ($values as $i => $value) {
            $cells .= $this->cell(
                $value,
                self::COLUMN_WIDTHS[$i],
                align: in_array($i, [0, 3, 4], true) ? 'center' : 'left',
            );
        }

        return "<w:tr>{$cells}</w:tr>";
    }

    private function cell(
        string $text,
        int $width,
        bool $bold = false,
        string $align = 'left',
        ?string $shade = null,
    ): string {
        $shading = $shade
            ? "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"{$shade}\"/>"
            : '';

        $runProps = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return '<w:tc><w:tcPr>'
            ."<w:tcW w:w=\"{$width}\" w:type=\"dxa\"/>{$shading}"
            .'<w:vAlign w:val="center"/></w:tcPr>'
            ."<w:p><w:pPr><w:jc w:val=\"{$align}\"/></w:pPr>"
            ."<w:r>{$runProps}<w:t xml:space=\"preserve\">".$this->escape($text).'</w:t></w:r>'
            .'</w:p></w:tc>';
    }

    private function sectionProperties(): string
    {
        // A4 босоо, 2 см захтай.
        return '<w:sectPr>'
            .'<w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/>'
            .'</w:sectPr>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
