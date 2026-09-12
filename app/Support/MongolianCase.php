<?php

namespace App\Support;

/**
 * Монгол хэлний тийн ялгалын нөхцөлийг үгэнд зөв залгана.
 *
 * Одоогоор харьяалахын тийн ялгал (-ын / -ийн / -ы / -ий / -ны / -ний)
 * дэмжинэ. Байгууллагын нэр нь товчлол, бүтэн үг аль аль нь байдаг тул
 * гурван эх сурвалжийг дараалан шалгана:
 *
 * 1. Товчлол (ЗДТГ, СХЗХ) — зурааснаас хойш залгана: «ЗДТГ-ын».
 * 2. Түгээмэл үгийн жагсаалт — «хэлтэс» → «хэлтсийн» гэх мэт.
 * 3. Ерөнхий дүрэм — эгшгийн зохицол, тогтворгүй эгшгийн уналт.
 */
class MongolianCase
{
    /** Эрэгтэй (хатуу) эгшиг. */
    private const BACK_VOWELS = ['а', 'о', 'у', 'я', 'ё'];

    /** Эгшиг үсгүүд. */
    private const VOWELS = ['а', 'э', 'и', 'о', 'ө', 'у', 'ү', 'ы', 'я', 'е', 'ё', 'ю'];

    /** Тогтворгүй эгшиг — нөхцөл залгахад унадаг. */
    private const UNSTABLE = ['а', 'э', 'о', 'ө', 'у', 'ү'];

    /** Тохиргоонд хадгалагдах түлхүүр. */
    public const SETTING_KEY = 'mongolian.genitive';

    /**
     * Дүрмээр гаргахад хүндрэлтэй, түгээмэл тохиолддог үгс.
     *
     * Системийн тохиргооноос нэмж, засаж болно — эндэх нь анхны утга.
     *
     * @var array<string, string>
     */
    public const EXCEPTIONS = [
        'хэлтэс' => 'хэлтсийн',
        'газар' => 'газрын',
        'алба' => 'албаны',
        'албан' => 'албаны',
        'товчоо' => 'товчооны',
        'хороо' => 'хорооны',
        'зөвлөл' => 'зөвлөлийн',
        'яам' => 'яамны',
        'сургууль' => 'сургуулийн',
        'эмнэлэг' => 'эмнэлгийн',
        'цэцэрлэг' => 'цэцэрлэгийн',
        'тасаг' => 'тасгийн',
        'агентлаг' => 'агентлагийн',
        'аймаг' => 'аймгийн',
        'сум' => 'сумын',
        'төв' => 'төвийн',
        'захиргаа' => 'захиргааны',
        'удирдлага' => 'удирдлагын',
        'байгууллага' => 'байгууллагын',
        'хүрээлэн' => 'хүрээлэнгийн',
        'нэгж' => 'нэгжийн',
        'групп' => 'группийн',
        'компани' => 'компанийн',
        'сан' => 'сангийн',
        'хурал' => 'хурлын',
        'тамга' => 'тамгын',
        'захирагч' => 'захирагчийн',
        'дарга' => 'даргын',
    ];

    /**
     * Харьяалахын тийн ялгал.
     *
     * Хэллэг өгвөл зөвхөн сүүлийн үгэнд нь залгана — «Төрийн захиргааны
     * удирдлагын хэлтэс» → «… удирдлагын хэлтсийн».
     */
    public static function genitive(string $phrase): string
    {
        $phrase = trim($phrase);

        if ($phrase === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $phrase) ?: [$phrase];
        $last = (string) array_pop($parts);

        $inflected = self::genitiveWord($last);

        $parts[] = $inflected;

        return implode(' ', $parts);
    }

    /** Нэг үгэнд залгана. */
    public static function genitiveWord(string $word): string
    {
        $word = trim($word);

        if ($word === '') {
            return '';
        }

        // Аль хэдийн харьяалахын хэлбэртэй бол дахин залгахгүй.
        foreach (['ын', 'ийн', 'ы', 'ий', 'ны', 'ний', 'гийн', 'гын'] as $ending) {
            if (mb_strlen($word) > mb_strlen($ending) + 1 && str_ends_with(mb_strtolower($word), $ending)) {
                return $word;
            }
        }

        if (self::isAbbreviation($word)) {
            return $word.'-'.(self::isBack($word) ? 'ын' : 'ийн');
        }

        $lower = mb_strtolower($word);
        $exceptions = self::exceptions();

        if (isset($exceptions[$lower])) {
            return self::matchCase($word, $exceptions[$lower]);
        }

        return self::matchCase($word, self::byRule($lower));
    }

    /**
     * Хэрэглэгдэх үгийн жагсаалт — анхны утга дээр тохиргоог нэмнэ.
     *
     * @return array<string, string>
     */
    public static function exceptions(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $saved = [];

        try {
            $raw = \App\Models\AppSetting::query()->where('key', self::SETTING_KEY)->value('value');
            $decoded = $raw ? json_decode((string) $raw, true) : null;

            if (is_array($decoded)) {
                foreach ($decoded as $word => $form) {
                    $word = mb_strtolower(trim((string) $word));
                    $form = trim((string) $form);

                    if ($word !== '' && $form !== '') {
                        $saved[$word] = $form;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Хүснэгт бэлэн биш (жишээ нь migration-аас өмнө) — анхны утгаар.
            $saved = [];
        }

        return self::$cache = array_merge(self::EXCEPTIONS, $saved);
    }

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** Тестийн хооронд, хадгалсны дараа цээжилсэн жагсаалтыг хаяна. */
    public static function forget(): void
    {
        self::$cache = null;
    }

    /** Товчлол эсэх — үсэг нь бүгд том. */
    private static function isAbbreviation(string $word): bool
    {
        $letters = preg_replace('/[^\p{L}]+/u', '', $word) ?? '';

        return mb_strlen($letters) >= 2 && $letters === mb_strtoupper($letters);
    }

    /**
     * Эгшгийн зохицол — хатуу (эрэгтэй) эсэх.
     *
     * Сүүлчийн зохицол тээгч эгшгээр шийднэ. «и», «е» нь саармаг тул
     * тооцохгүй. Эгшиггүй товчлолд (ЗДТГ, СХЗХ) хатуу гэж үзнэ —
     * «ЗДТГ-ын» гэж бичдэг.
     */
    private static function isBack(string $word): bool
    {
        $letters = preg_split('//u', mb_strtolower($word), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        for ($i = count($letters) - 1; $i >= 0; $i--) {
            $letter = $letters[$i];

            if (in_array($letter, self::BACK_VOWELS, true)) {
                return true;
            }

            if (in_array($letter, ['э', 'ө', 'ү', 'ю'], true)) {
                return false;
            }
        }

        return true;
    }

    /** Ерөнхий дүрэм. */
    private static function byRule(string $word): string
    {
        $back = self::isBack($word);
        $last = mb_substr($word, -1);

        // Зөөлний тэмдэг: «сургууль» → «сургуулийн».
        if ($last === 'ь') {
            return mb_substr($word, 0, -1).'ийн';
        }

        // и, й, ы-гаар төгссөн: «Дэлхий» → «Дэлхийн».
        if (in_array($last, ['й', 'ы'], true)) {
            return $word.'н';
        }

        if ($last === 'и') {
            return $word.'йн';
        }

        // н-ээр төгссөн: «ажилтан» → «ажилтны» биш «ажилтан» → «ажилтны».
        if ($last === 'н') {
            return self::dropUnstable($word).($back ? 'ы' : 'ий');
        }

        if (in_array($last, self::VOWELS, true)) {
            $prev = mb_substr($word, -2, 1);

            // Урт эгшиг — далд «н» гарч ирнэ: «хороо» → «хорооны».
            if ($prev === $last) {
                return $word.($back ? 'ны' : 'ний');
            }

            return $word.($back ? 'гын' : 'гийн');
        }

        $stem = self::dropUnstable($word);

        // г-ээр төгссөн үгэнд үргэлж «ийн»: «аймаг» → «аймгийн».
        if (mb_substr($stem, -1) === 'г') {
            return $stem.'ийн';
        }

        return $stem.($back ? 'ын' : 'ийн');
    }

    /**
     * Тогтворгүй эгшгийг унагана: «хэлтэс» → «хэлтс», «газар» → «газр».
     *
     * Хоёроос доош үетэй үгэнд хэрэглэхгүй — «ном» гэх мэт богино үг
     * эгшгээ алдах ёсгүй.
     */
    private static function dropUnstable(string $word): string
    {
        $length = mb_strlen($word);

        if ($length < 4) {
            return $word;
        }

        $last = mb_substr($word, -1);
        $vowel = mb_substr($word, -2, 1);
        $before = mb_substr($word, -3, 1);

        $isConsonant = fn (string $c) => ! in_array($c, self::VOWELS, true) && $c !== 'ь' && $c !== 'ъ';

        if ($isConsonant($last) && in_array($vowel, self::UNSTABLE, true) && $isConsonant($before)) {
            return mb_substr($word, 0, -2).$last;
        }

        return $word;
    }

    /** Эх үгийн том/жижиг үсгийн хэлбэрийг хадгална. */
    private static function matchCase(string $original, string $inflected): string
    {
        $first = mb_substr($original, 0, 1);

        if ($first === mb_strtoupper($first) && $first !== mb_strtolower($first)) {
            return mb_strtoupper(mb_substr($inflected, 0, 1)).mb_substr($inflected, 1);
        }

        return $inflected;
    }
}
