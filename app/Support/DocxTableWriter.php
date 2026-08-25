<?php

namespace App\Support;

use App\Models\DocumentFormat;
use RuntimeException;
use ZipArchive;

/**
 * Энгийн нэг хүснэгттэй Word (.docx) файл бичнэ.
 *
 * Гадны сан ашиглахгүй — OOXML-ийн хамгийн бага бүтцийг өөрөө үүсгэнэ.
 */
class DocxTableWriter
{
    /**
     * @param  array<int, string>  $headings  Баганын нэрс
     * @param  array<int, int>  $widths  Баганын өргөн (twip)
     * @param  array<int, array{type?: string, text?: string, cells?: array<int, string>}>  $rows
     * @param  array<int, int>  $centerColumns  Голлуулж бичих баганын индекс
     */
    public function write(
        string $path,
        string $title,
        array $headings,
        array $widths,
        array $rows,
        array $centerColumns = [],
        bool $landscape = false,
        ?DocumentFormat $format = null,
    ): void {
        // Бичиг хэргийн стандартаас хуудасны тохиргоог авна.
        $format ??= DocumentFormat::defaultFormat();
        $widths = $this->fitWidths($widths, $format, $landscape);

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Word файл үүсгэж чадсангүй.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/styles.xml', $this->styles($format));
        $zip->addFromString('word/document.xml',
            $this->document($title, $headings, $widths, $rows, $centerColumns, $landscape, $format));
        $zip->close();
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
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
     * @param  array<int, string>  $headings
     * @param  array<int, int>  $widths
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $centerColumns
     */
    private function document(
        string $title,
        array $headings,
        array $widths,
        array $rows,
        array $centerColumns,
        bool $landscape,
        ?DocumentFormat $format,
    ): string {
        $body = $this->heading($title);
        $body .= '<w:tbl>'.$this->tableProperties().$this->headerRow($headings, $widths);

        foreach ($rows as $row) {
            $body .= ($row['type'] ?? 'data') === 'group'
                ? $this->groupRow((string) ($row['text'] ?? ''), $widths, count($headings))
                : $this->dataRow(array_values($row['cells'] ?? []), $widths, $centerColumns);
        }

        $body .= '</w:tbl>'.$this->sectionProperties($landscape, $format);

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

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, int>  $widths
     */
    private function headerRow(array $headings, array $widths): string
    {
        $cells = '';

        foreach ($headings as $i => $label) {
            $cells .= $this->cell($label, $widths[$i] ?? 1500, bold: true, align: 'center', shade: 'DCE6F1');
        }

        return '<w:tr><w:trPr><w:tblHeader/></w:trPr>'.$cells.'</w:tr>';
    }

    /**
     * @param  array<int, int>  $widths
     */
    private function groupRow(string $text, array $widths, int $columnCount): string
    {
        $width = array_sum($widths);

        $cell = '<w:tc><w:tcPr>'
            ."<w:tcW w:w=\"{$width}\" w:type=\"dxa\"/>"
            ."<w:gridSpan w:val=\"{$columnCount}\"/>"
            .'<w:shd w:val="clear" w:color="auto" w:fill="F2F2F2"/>'
            .'</w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:i/></w:rPr><w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>'
            .'</w:p></w:tc>';

        return "<w:tr>{$cell}</w:tr>";
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, int>  $widths
     * @param  array<int, int>  $centerColumns
     */
    private function dataRow(array $values, array $widths, array $centerColumns): string
    {
        $cells = '';

        foreach ($values as $i => $value) {
            $cells .= $this->cell(
                (string) $value,
                $widths[$i] ?? 1500,
                align: in_array($i, $centerColumns, true) ? 'center' : 'left',
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

    /**
     * Хуудасны хэмжээ, зах — бичиг хэргийн стандартаас.
     */
    private function sectionProperties(bool $landscape, ?DocumentFormat $format): string
    {
        $width = $format ? $format->pageWidthTwip($landscape) : ($landscape ? 16838 : 11906);
        $height = $format ? $format->pageHeightTwip($landscape) : ($landscape ? 11906 : 16838);
        $orient = $landscape ? ' w:orient="landscape"' : '';

        $top = $format ? DocumentFormat::toTwip($format->margin_top_mm) : 1134;
        $right = $format ? DocumentFormat::toTwip($format->margin_right_mm) : 1134;
        $bottom = $format ? DocumentFormat::toTwip($format->margin_bottom_mm) : 1134;
        $left = $format ? DocumentFormat::toTwip($format->margin_left_mm) : 1134;

        return '<w:sectPr>'
            ."<w:pgSz w:w=\"{$width}\" w:h=\"{$height}\"{$orient}/>"
            ."<w:pgMar w:top=\"{$top}\" w:right=\"{$right}\" w:bottom=\"{$bottom}\" w:left=\"{$left}\" w:header=\"708\" w:footer=\"708\" w:gutter=\"0\"/>"
            .'</w:sectPr>';
    }

    /**
     * Фонт, үсгийн хэмжээ, мөр хоорондын зайг баримтын үндсэн загвар болгоно.
     */
    private function styles(?DocumentFormat $format): string
    {
        $font = $this->escape($format?->font_name ?: 'Arial');
        $size = $format?->fontHalfPoints() ?: 24;
        $spacing = $format?->lineSpacingTwip() ?: 240;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'
            ."<w:rFonts w:ascii=\"{$font}\" w:hAnsi=\"{$font}\" w:cs=\"{$font}\"/>"
            ."<w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/>"
            .'</w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr>'
            ."<w:spacing w:after=\"0\" w:line=\"{$spacing}\" w:lineRule=\"auto\"/>"
            .'</w:pPr></w:pPrDefault></w:docDefaults></w:styles>';
    }

    /**
     * Баганын өргөнийг хуудасны цэвэр өргөнд багтаана.
     *
     * @param  array<int, int>  $widths
     * @return array<int, int>
     */
    private function fitWidths(array $widths, ?DocumentFormat $format, bool $landscape): array
    {
        if (! $format || ! $widths) {
            return $widths;
        }

        $total = array_sum($widths);
        $available = $format->contentWidthTwip($landscape);

        if ($total <= 0 || $total === $available) {
            return $widths;
        }

        $ratio = $available / $total;

        return array_map(fn (int $w) => max(300, (int) round($w * $ratio)), $widths);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
