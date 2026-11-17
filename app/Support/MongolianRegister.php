<?php

namespace App\Support;

/**
 * Монгол регистрийн дугаараас төрсөн өдөр, нас, хүйс гаргана.
 *
 * Хэлбэр: 2 үсэг + 8 орон (Жишээ: УБ85031512).
 * Огноо: ЖЖССӨӨ, хүйс: дарааллын дугаарын тэгш/сонгоноор.
 */
class MongolianRegister
{
    /** @var array<string, string> */
    private const LATIN_TO_CYRILLIC = [
        'A' => 'А', 'B' => 'Б', 'V' => 'В', 'G' => 'Г', 'D' => 'Д',
        'E' => 'Е', 'J' => 'Ж', 'Z' => 'З', 'I' => 'И', 'K' => 'К',
        'L' => 'Л', 'M' => 'М', 'N' => 'Н', 'O' => 'О', 'P' => 'П',
        'R' => 'Р', 'S' => 'С', 'T' => 'Т', 'U' => 'У', 'F' => 'Ф',
        'H' => 'Х', 'C' => 'Ц', 'W' => 'Ш', 'Y' => 'Ү', 'X' => 'Ө',
        'Q' => 'Ө',
    ];

    /**
     * @return array{register: string, birth: string, age: int, gender: string}|null
     */
    public static function parse(?string $value, ?int $asOfYear = null): ?array
    {
        $register = self::normalize($value);

        if ($register === null) {
            return null;
        }

        $digits = mb_substr($register, 2);
        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        $seq = (int) substr($digits, 6, 2);

        $asOfYear ??= (int) date('Y');
        $currentYy = $asOfYear % 100;
        $year = $yy + ($yy <= $currentYy ? 2000 : 1900);

        if (! checkdate($mm, $dd, $year)) {
            return null;
        }

        $age = $asOfYear - $year;

        if ($age < 0 || $age > 120) {
            return null;
        }

        return [
            'register' => $register,
            'birth' => sprintf('%04d-%02d-%02d', $year, $mm, $dd),
            'age' => $age,
            'gender' => ($seq % 2 === 1) ? 'эр' : 'эм',
        ];
    }

    public static function normalize(?string $value): ?string
    {
        $clean = preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $value)));

        if ($clean === null || $clean === '') {
            return null;
        }

        $letters = '';

        foreach (mb_str_split(mb_substr($clean, 0, 2)) as $ch) {
            $letters .= self::LATIN_TO_CYRILLIC[$ch] ?? $ch;
        }

        $digits = preg_replace('/\D+/', '', mb_substr($clean, 2)) ?? '';

        if (strlen($digits) > 8) {
            $digits = substr($digits, 0, 8);
        }

        $register = $letters.$digits;

        return preg_match('/^[\x{0410}-\x{042F}\x{0401}\x{04AE}\x{04E8}]{2}\d{8}$/u', $register)
            ? $register
            : null;
    }
}
