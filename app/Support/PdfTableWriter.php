<?php

namespace App\Support;

use RuntimeException;

/**
 * Хүснэгтийг PDF болгож бичнэ (GD + TrueType → JPEG хуудас).
 *
 * Гадны PDF сан ашиглахгүй. Монгол кирилл фонт системээс авна.
 */
class PdfTableWriter
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function write(
        string $path,
        string $title,
        array $headings,
        array $rows,
        bool $landscape = false,
    ): void {
        $pages = $this->renderPages($title, $headings, $rows, $landscape, $this->fontPath());
        $pdf = $this->buildPdf($pages, $landscape);

        if (file_put_contents($path, $pdf) === false) {
            throw new RuntimeException('PDF файл үүсгэж чадсангүй.');
        }
    }

    private function fontPath(): string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\tahoma.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        throw new RuntimeException('PDF-д хэрэгтэй TrueType фонт олдсонгүй (Arial/DejaVu).');
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @return array<int, string>
     */
    private function renderPages(
        string $title,
        array $headings,
        array $rows,
        bool $landscape,
        string $font,
    ): array {
        $dpi = 150;
        $pageW = (int) round(($landscape ? 297 : 210) / 25.4 * $dpi);
        $pageH = (int) round(($landscape ? 210 : 297) / 25.4 * $dpi);
        $margin = (int) round(12 / 25.4 * $dpi);
        $colCount = max(1, count($headings));
        $colW = (int) floor(($pageW - ($margin * 2)) / $colCount);
        $fontSize = $landscape && $colCount > 10 ? 8.0 : 9.5;
        $pad = 4;
        $lineH = (int) ceil($fontSize * 1.45) + $pad;

        $stringRows = array_map(
            fn (array $row) => array_pad(
                array_map(fn ($v) => (string) ($v ?? ''), array_slice($row, 0, $colCount)),
                $colCount,
                '',
            ),
            $rows,
        );

        $pages = [];
        $img = $this->createPage($pageW, $pageH, $title, $font, $margin);
        $y = $margin + 48;
        $y = $this->drawHeaderRow($img, $headings, $y, $margin, $colW, $font, $fontSize, $pad, $lineH, $pageW);

        foreach ($stringRows as $cells) {
            $rowH = $this->measureRowHeight($cells, $colW, $font, $fontSize, $pad, $lineH);

            if ($y + $rowH > $pageH - $margin) {
                $pages[] = $this->jpeg($img);
                imagedestroy($img);
                $img = $this->createPage($pageW, $pageH, $title, $font, $margin);
                $y = $margin + 48;
                $y = $this->drawHeaderRow($img, $headings, $y, $margin, $colW, $font, $fontSize, $pad, $lineH, $pageW);
            }

            $x = $margin;
            $black = imagecolorallocate($img, 20, 20, 20);

            foreach ($cells as $text) {
                $this->drawCell($img, $text, $x, $y, $colW, $rowH, $font, $fontSize, $pad, $black, false);
                $x += $colW;
            }

            $y += $rowH;
        }

        if (! $stringRows) {
            $gray = imagecolorallocate($img, 100, 100, 100);
            imagettftext($img, $fontSize, 0, $margin, $y + 20, $gray, $font, 'Бүртгэл алга.');
        }

        $pages[] = $this->jpeg($img);
        imagedestroy($img);

        return $pages;
    }

    /**
     * @return \GdImage
     */
    private function createPage(int $pageW, int $pageH, string $title, string $font, int $margin)
    {
        $img = imagecreatetruecolor($pageW, $pageH);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 20, 20, 20);
        $gray = imagecolorallocate($img, 100, 100, 100);
        imagefilledrectangle($img, 0, 0, $pageW, $pageH, $white);

        $box = imagettfbbox(12.0, 0, $font, $title);
        $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
        imagettftext($img, 12.0, 0, (int) (($pageW - $tw) / 2), $margin + 16, $black, $font, $title);

        $meta = now()->format('Y-m-d');
        $mbox = imagettfbbox(8.0, 0, $font, $meta);
        $mw = abs(($mbox[2] ?? 0) - ($mbox[0] ?? 0));
        imagettftext($img, 8.0, 0, (int) (($pageW - $mw) / 2), $margin + 34, $gray, $font, $meta);

        return $img;
    }

    /**
     * @param  \GdImage  $img
     * @param  array<int, string>  $headings
     */
    private function drawHeaderRow(
        $img,
        array $headings,
        int $y,
        int $margin,
        int $colW,
        string $font,
        float $fontSize,
        int $pad,
        int $lineH,
        int $pageW,
    ): int {
        $rowH = $this->measureRowHeight($headings, $colW, $font, $fontSize, $pad, $lineH);
        $headerBg = imagecolorallocate($img, 220, 230, 241);
        $black = imagecolorallocate($img, 20, 20, 20);
        imagefilledrectangle($img, $margin, $y, $pageW - $margin, $y + $rowH, $headerBg);

        $x = $margin;
        foreach ($headings as $text) {
            $this->drawCell($img, $text, $x, $y, $colW, $rowH, $font, $fontSize, $pad, $black, true);
            $x += $colW;
        }

        return $y + $rowH;
    }

    /**
     * @param  \GdImage  $img
     */
    private function jpeg($img): string
    {
        ob_start();
        imagejpeg($img, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function measureRowHeight(
        array $cells,
        int $colW,
        string $font,
        float $fontSize,
        int $pad,
        int $minH,
    ): int {
        $maxLines = 1;

        foreach ($cells as $text) {
            $maxLines = max($maxLines, count($this->wrapText((string) $text, $colW - ($pad * 2), $font, $fontSize)));
        }

        return max($minH, (int) ceil($maxLines * ($fontSize * 1.35)) + ($pad * 2));
    }

    /**
     * @return array<int, string>
     */
    private function wrapText(string $text, int $maxW, string $font, float $fontSize): array
    {
        $text = trim(preg_replace("/\s+/u", ' ', $text) ?? $text);

        if ($text === '') {
            return [''];
        }

        $words = preg_split('/(?<=\s)/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $trial = $current.$word;
            $box = imagettfbbox($fontSize, 0, $font, $trial);
            $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));

            if ($current !== '' && $w > $maxW) {
                $lines[] = rtrim($current);
                $current = ltrim($word);
            } else {
                $current = $trial;
            }
        }

        if ($current !== '') {
            $lines[] = rtrim($current);
        }

        return $lines ?: [''];
    }

    /**
     * @param  \GdImage  $img
     */
    private function drawCell(
        $img,
        string $text,
        int $x,
        int $y,
        int $w,
        int $h,
        string $font,
        float $fontSize,
        int $pad,
        int $color,
        bool $center,
    ): void {
        $border = imagecolorallocate($img, 30, 30, 30);
        imagerectangle($img, $x, $y, $x + $w, $y + $h, $border);

        $lines = $this->wrapText($text, $w - ($pad * 2), $font, $fontSize);
        $lineH = (int) ceil($fontSize * 1.35);
        $ty = $y + (int) ((($h - (count($lines) * $lineH)) / 2) + $fontSize);

        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $font, $line);
            $tw = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            $tx = $center ? $x + (int) (($w - $tw) / 2) : $x + $pad;
            imagettftext($img, $fontSize, 0, max($x + 1, $tx), $ty, $color, $font, $line);
            $ty += $lineH;
        }
    }

    /**
     * @param  array<int, string>  $jpegPages
     */
    private function buildPdf(array $jpegPages, bool $landscape): string
    {
        $pageW = $landscape ? 841.89 : 595.28;
        $pageH = $landscape ? 595.28 : 841.89;
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        $objNum = 3;

        foreach (array_values($jpegPages) as $i => $jpeg) {
            $size = @getimagesizefromstring($jpeg);
            $iw = $size[0] ?? 1;
            $ih = $size[1] ?? 1;
            $len = strlen($jpeg);

            $imgObj = $objNum++;
            $contentObj = $objNum++;
            $pageObj = $objNum++;
            $kids[] = $pageObj.' 0 R';

            $objects[$imgObj] = "<< /Type /XObject /Subtype /Image /Width {$iw} /Height {$ih}"
                ." /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$len} >>"
                ."\nstream\n{$jpeg}\nendstream";

            $content = "q {$pageW} 0 0 {$pageH} 0 0 cm /Im{$i} Do Q";
            $clen = strlen($content);
            $objects[$contentObj] = "<< /Length {$clen} >>\nstream\n{$content}\nendstream";
            $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}]"
                ." /Contents {$contentObj} 0 R"
                ." /Resources << /XObject << /Im{$i} {$imgObj} 0 R >> >> >>";
        }

        if (! $kids) {
            throw new RuntimeException('PDF хуудас үүссэнгүй.');
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 ".($max + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
