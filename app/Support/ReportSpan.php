<?php

namespace App\Support;

/**
 * Хэсгийн гарчиг мөрийг бүх баганаар нэгтгэнэ.
 * Жишээ нь «3.1.2. Гэр бүлийн хүчирхийлэлтэй тэмцэх талаар:».
 */
class ReportSpan
{
    public const COLUMN = '_span';

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function apply(array $row): array
    {
        $flag = $row[self::COLUMN] ?? null;
        $forced = $flag === '1' || $flag === 1 || $flag === true;
        $blocked = $flag === '0' || $flag === 0 || $flag === false;

        $row[self::COLUMN] = $forced || (! $blocked && self::auto($row));
        $row['_span_text'] = self::label($row);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function auto(array $row): bool
    {
        $filled = self::filled($row);
        if ($filled === []) {
            return false;
        }

        $onlyTitle = array_diff(array_keys($filled), ['no', 'document']) === [];
        if (! $onlyTitle) {
            return false;
        }

        $document = $filled['document'] ?? '';

        return preg_match('/^\d+(?:\.\d+)+\.?\s+\S/u', $document) === 1;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function label(array $row): string
    {
        $filled = self::filled($row);
        $document = $filled['document'] ?? '';
        $no = $filled['no'] ?? '';

        if ($document !== '' && ($no === '' || str_starts_with($document, rtrim($no, '.')))) {
            unset($filled['no']);
        }

        return implode("\n", array_values($filled));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function filled(array $row): array
    {
        $skip = [self::COLUMN, '_span_text', '_index', 'department_id'];
        $filled = [];

        foreach ($row as $key => $value) {
            if (! is_string($key) || in_array($key, $skip, true)) {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '') {
                $filled[$key] = $text;
            }
        }

        return $filled;
    }
}
