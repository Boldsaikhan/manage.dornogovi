<?php

namespace App\Support;

use RuntimeException;

/**
 * PDF файлаас текстийг байрлалтай нь уншиж, мөр/баганад хуваана.
 *
 * Зөвхөн текст агуулсан (сканнердсан зураг биш) PDF-д ажиллана.
 * Гадны сан ашиглахгүй — контент урсгалыг задалж, текстийн операторуудыг уншина.
 */
class PdfTableReader
{
    /** Нэг мөрд тооцох босоо зөрүү. */
    private const LINE_TOLERANCE = 3.0;

    /** Нүд салгах хэвтээ зай. */
    private const COLUMN_GAP = 9.0;

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(string $path): array
    {
        $raw = @file_get_contents($path);

        if ($raw === false || ! str_starts_with($raw, '%PDF')) {
            throw new RuntimeException('PDF файлыг уншиж чадсангүй.');
        }

        $objects = $this->objects($raw);
        $fonts = $this->fontMaps($objects);
        $items = [];

        foreach ($objects as $object) {
            $content = $this->streamContent($object);

            if ($content === null || ! str_contains($content, 'BT')) {
                continue;
            }

            foreach ($this->textItems($content, $fonts) as $item) {
                $items[] = $item;
            }
        }

        return $this->toRows($items);
    }

    /**
     * @return array<int, string> Обьектийн бүтэн эх (dict + stream)
     */
    private function objects(string $raw): array
    {
        preg_match_all('/\d+\s+\d+\s+obj(.*?)endobj/s', $raw, $matches);

        return $matches[1] ?? [];
    }

    private function streamContent(string $object): ?string
    {
        if (! preg_match('/stream\r?\n?(.*?)endstream/s', $object, $m)) {
            return null;
        }

        $data = $m[1];

        if (str_contains($object, '/FlateDecode')) {
            $inflated = @gzuncompress($data);

            if ($inflated === false) {
                $inflated = @gzinflate($data);
            }

            if ($inflated === false) {
                return null;
            }

            $data = $inflated;
        }

        return $data;
    }

    /**
     * Фонт бүрийн ToUnicode хөрвүүлэлт: fontName → [codeLength, [code => text]].
     *
     * @param  array<int, string>  $objects
     * @return array<string, array{length: int, map: array<int, string>}>
     */
    private function fontMaps(array $objects): array
    {
        $cmaps = [];

        foreach ($objects as $object) {
            $content = $this->streamContent($object);

            if ($content === null || ! str_contains($content, 'beginbfchar') && ! str_contains($content, 'beginbfrange')) {
                continue;
            }

            $cmaps[] = $this->parseCMap($content);
        }

        if (! $cmaps) {
            return [];
        }

        // Нэг документэд ихэвчлэн нэг л кодчлол давамгайлдаг тул нэгтгэнэ.
        $merged = ['length' => 2, 'map' => []];

        foreach ($cmaps as $cmap) {
            $merged['length'] = $cmap['length'];
            $merged['map'] += $cmap['map'];
        }

        return ['*' => $merged];
    }

    /**
     * @return array{length: int, map: array<int, string>}
     */
    private function parseCMap(string $content): array
    {
        $map = [];
        $length = 2;

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $content, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);

                foreach ($pairs as $pair) {
                    $length = max(1, (int) (strlen($pair[1]) / 2));
                    $map[hexdec($pair[1])] = $this->utf16BeToUtf8($pair[2]);
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $content, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER);

                foreach ($ranges as $range) {
                    $length = max(1, (int) (strlen($range[1]) / 2));
                    $from = hexdec($range[1]);
                    $to = hexdec($range[2]);
                    $target = hexdec($range[3]);

                    for ($code = $from; $code <= $to && $code - $from < 65535; $code++) {
                        $map[$code] = mb_convert_encoding(
                            pack('n', $target + ($code - $from)),
                            'UTF-8',
                            'UTF-16BE'
                        );
                    }
                }
            }
        }

        return ['length' => $length, 'map' => $map];
    }

    private function utf16BeToUtf8(string $hex): string
    {
        $bytes = hex2bin(strlen($hex) % 2 === 0 ? $hex : '0'.$hex);

        if ($bytes === false) {
            return '';
        }

        return (string) mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
    }

    /**
     * Контент урсгалаас текстийн хэсгүүдийг байрлалтай нь гаргана.
     *
     * @param  array<string, array{length: int, map: array<int, string>}>  $fonts
     * @return array<int, array{x: float, y: float, text: string, size: float}>
     */
    private function textItems(string $content, array $fonts): array
    {
        $items = [];
        $x = $y = 0.0;
        $lineX = $lineY = 0.0;
        $leading = 0.0;
        $size = 10.0;

        // Операторуудыг мөр мөрөөр нь уншина.
        $tokens = preg_split('/(?<=\bBT\b|\bET\b|\bTd\b|\bTD\b|\bTm\b|\bT\*\b|\bTj\b|\bTJ\b|\bTf\b|\bTL\b|\'|")\s*/', $content) ?: [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            if (preg_match('/([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+Tm$/', $token, $m)) {
                $x = $lineX = (float) $m[5];
                $y = $lineY = (float) $m[6];
                $size = abs((float) $m[1]) ?: $size;

                continue;
            }

            if (preg_match('/([\d.\-]+)\s+([\d.\-]+)\s+(Td|TD)$/', $token, $m)) {
                $x = $lineX += (float) $m[1];
                $y = $lineY += (float) $m[2];

                if ($m[3] === 'TD') {
                    $leading = -(float) $m[2];
                }

                continue;
            }

            if (preg_match('/([\d.\-]+)\s+TL$/', $token, $m)) {
                $leading = (float) $m[1];

                continue;
            }

            if (preg_match('#/\S+\s+([\d.]+)\s+Tf$#', $token, $m)) {
                $size = (float) $m[1] ?: $size;

                continue;
            }

            if (str_ends_with($token, 'T*')) {
                $x = $lineX;
                $y = $lineY -= $leading;

                continue;
            }

            if (preg_match('/^(.*)(Tj|TJ|\'|")$/s', $token, $m)) {
                if ($m[2] === "'" || $m[2] === '"') {
                    $x = $lineX;
                    $y = $lineY -= $leading;
                }

                $text = $this->decodeShowText($m[1], $fonts);

                if (trim($text) !== '') {
                    $items[] = ['x' => $x, 'y' => $y, 'text' => trim($text), 'size' => $size];
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, array{length: int, map: array<int, string>}>  $fonts
     */
    private function decodeShowText(string $operand, array $fonts): string
    {
        $out = '';

        preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<[0-9A-Fa-f\s]+>/s', $operand, $matches);

        foreach ($matches[0] as $chunk) {
            $out .= str_starts_with($chunk, '<')
                ? $this->decodeHexString($chunk, $fonts)
                : $this->decodeLiteralString($chunk, $fonts);
        }

        return $out;
    }

    /**
     * @param  array<string, array{length: int, map: array<int, string>}>  $fonts
     */
    private function decodeHexString(string $chunk, array $fonts): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $chunk) ?? '';
        $font = $fonts['*'] ?? null;
        $length = $font['length'] ?? 2;
        $step = max(1, $length) * 2;
        $out = '';

        for ($i = 0; $i + $step <= strlen($hex); $i += $step) {
            $code = hexdec(substr($hex, $i, $step));
            $out .= $font['map'][$code] ?? $this->fallbackChar($code);
        }

        return $out;
    }

    /**
     * @param  array<string, array{length: int, map: array<int, string>}>  $fonts
     */
    private function decodeLiteralString(string $chunk, array $fonts): string
    {
        $body = substr($chunk, 1, -1);
        $body = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function (array $m) {
            return match ($m[1]) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                '(' => '(', ')' => ')', '\\' => '\\',
                default => chr((int) octdec($m[1])),
            };
        }, $body) ?? $body;

        $font = $fonts['*'] ?? null;

        if ($font && $font['map']) {
            $out = '';

            foreach (str_split($body) as $char) {
                $out .= $font['map'][ord($char)] ?? $char;
            }

            return $out;
        }

        return $body;
    }

    private function fallbackChar(int $code): string
    {
        return $code >= 32 && $code < 127 ? chr($code) : '';
    }

    /**
     * Текстийн хэсгүүдийг мөр, нүд болгож эмхэлнэ.
     *
     * @param  array<int, array{x: float, y: float, text: string, size: float}>  $items
     * @return array<int, array<int, string>>
     */
    private function toRows(array $items): array
    {
        if (! $items) {
            return [];
        }

        usort($items, fn (array $a, array $b) => $b['y'] <=> $a['y'] ?: $a['x'] <=> $b['x']);

        $lines = [];
        $current = [];
        $lineY = null;

        foreach ($items as $item) {
            if ($lineY === null || abs($item['y'] - $lineY) <= self::LINE_TOLERANCE) {
                $lineY ??= $item['y'];
                $current[] = $item;

                continue;
            }

            $lines[] = $current;
            $current = [$item];
            $lineY = $item['y'];
        }

        if ($current) {
            $lines[] = $current;
        }

        $rows = [];

        foreach ($lines as $line) {
            usort($line, fn (array $a, array $b) => $a['x'] <=> $b['x']);

            $cells = [];
            $cell = '';
            $cellEnd = null;

            foreach ($line as $item) {
                $width = mb_strlen($item['text']) * $item['size'] * 0.5;

                if ($cellEnd !== null && $item['x'] - $cellEnd > self::COLUMN_GAP) {
                    $cells[] = trim($cell);
                    $cell = '';
                }

                $cell = $cell === '' ? $item['text'] : $cell.' '.$item['text'];
                $cellEnd = $item['x'] + $width;
            }

            if (trim($cell) !== '') {
                $cells[] = trim($cell);
            }

            if ($cells) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }
}
