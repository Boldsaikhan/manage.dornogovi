<?php

namespace App\Support;

/**
 * Хүний нэрийг албан бичгийн хэлбэрт оруулна: овгийн эхний үсэг + нэр.
 *
 * «Цагаанмаам Мөнхбат» → «Ц.Мөнхбат», «Ц. Мөнх-Эрдэнэ» → «Ц.Мөнх-Эрдэнэ»
 */
class PersonName
{
    /**
     * Нэг буюу олон нэрийг (« / »-ээр тусгаарласан) богиносгоно.
     */
    public static function shortList(?string $value): string
    {
        $names = preg_split('#[/;|]+#u', (string) $value) ?: [];

        $short = array_filter(array_map(
            fn (string $name) => self::short($name),
            $names
        ), fn (string $name) => $name !== '');

        return implode('/', $short);
    }

    /** Албан тушаал мөн эсэхийг таних түлхүүр үгс. */
    private const POSITION_WORDS = [
        'дарга', 'орлогч', 'мэргэжилтэн', 'нарийн бичиг', 'эрхлэгч', 'нягтлан',
        'ажилтан', 'зөвлөх', 'хэлтэс', 'газар', 'алба', 'сум', 'захирал',
        'эмч', 'багш', 'инженер', 'менежер', 'хурал', 'товчоо', 'төв',
    ];

    public static function short(?string $value): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if ($name === '' || ! self::looksLikePerson($name)) {
            return $name;
        }

        // Аль хэдийн «Ц.Мөнхбат» хэлбэртэй бол зөвхөн зайг цэвэрлэнэ.
        if (str_contains($name, '.')) {
            [$initial, $rest] = array_pad(explode('.', $name, 2), 2, '');
            $initial = trim($initial);
            $rest = trim($rest);

            if ($rest === '') {
                return $initial;
            }

            return mb_substr($initial, 0, 1).'.'.$rest;
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        if (count($parts) < 2) {
            return $name;
        }

        $last = array_shift($parts);
        $first = implode(' ', $parts);

        return mb_substr($last, 0, 1).'.'.$first;
    }

    /**
     * Албан тушаал, байгууллагын нэрийг богиносгохгүй.
     */
    private static function looksLikePerson(string $value): bool
    {
        $lower = mb_strtolower($value);

        foreach (self::POSITION_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        // Тоо, таслал агуулсан бол албан тушаал/байгууллагын нэр.
        if (preg_match('/[\d,]/u', $value)) {
            return false;
        }

        $words = preg_split('/\s+/u', $value) ?: [];

        // Нэр ихэвчлэн 1–3 үгтэй; товчлол (АЗДТГ, ИТХ) агуулбал нэр биш.
        if (count($words) > 3) {
            return false;
        }

        foreach ($words as $word) {
            $letters = preg_replace('/[^\p{L}]/u', '', $word);

            if (mb_strlen($letters) >= 2 && mb_strtoupper($letters) === $letters) {
                return false;
            }
        }

        return true;
    }
}
