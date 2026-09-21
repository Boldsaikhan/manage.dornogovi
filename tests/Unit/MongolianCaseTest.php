<?php

namespace Tests\Unit;

use App\Support\MongolianCase;
use PHPUnit\Framework\TestCase;

/**
 * Харьяалахын тийн ялгал.
 */
class MongolianCaseTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function words(): array
    {
        return [
            // Товчлол — зурааснаас хойш, эгшгийн зохицлоор.
            'ЗДТГ' => ['ЗДТГ', 'ЗДТГ-ын'],
            'СХЗХ' => ['СХЗХ', 'СХЗХ-ын'],
            'ХХҮГ' => ['ХХҮГ', 'ХХҮГ-ийн'],
            'МЭГ' => ['МЭГ', 'МЭГ-ийн'],

            // Тогтворгүй эгшиг унана.
            'хэлтэс' => ['хэлтэс', 'хэлтсийн'],
            'газар' => ['газар', 'газрын'],
            'аймаг' => ['аймаг', 'аймгийн'],
            'эмнэлэг' => ['эмнэлэг', 'эмнэлгийн'],
            'цэцэрлэг' => ['цэцэрлэг', 'цэцэрлэгийн'],

            // Жирийн гийгүүлэгч.
            'сум' => ['сум', 'сумын'],
            'төв' => ['төв', 'төвийн'],

            // Эгшиг, зөөлний тэмдэг.
            'сургууль' => ['сургууль', 'сургуулийн'],
            'захиргаа' => ['захиргаа', 'захиргааны'],
            'хороо' => ['хороо', 'хорооны'],
            'удирдлага' => ['удирдлага', 'удирдлагын'],

            // Том үсэг хадгалагдана.
            'Хэлтэс' => ['Хэлтэс', 'Хэлтсийн'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('words')]
    public function test_the_genitive_is_built_correctly(string $word, string $expected): void
    {
        $this->assertSame($expected, MongolianCase::genitiveWord($word));
    }

    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            // Гийгүүлэгчээр төгссөн — эгшгийн зохицлоор.
            'Батцэцэг' => ['Д.Батцэцэг', 'Д.Батцэцэгийг'],
            'Гарамжав' => ['Н.Гарамжав', 'Н.Гарамжавыг'],
            'Мөнхбат' => ['М.Мөнхбат', 'М.Мөнхбатыг'],
            'Номин' => ['А.Номин', 'А.Номиныг'],

            // Зөөлөн төгсгөл — үргэлж «ийг».
            'Дорж' => ['Б.Дорж', 'Б.Доржийг'],

            // Урт эгшиг хэвээрээ.
            'Алимаа' => ['Ш.Алимаа', 'Ш.Алимааг'],

            // Богино эгшиг унана.
            'Уянга' => ['А.Уянга', 'А.Уянгыг'],
            'Мөнх-Эрдэнэ' => ['Ц.Мөнх-Эрдэнэ', 'Ц.Мөнх-Эрдэнийг'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function test_the_accusative_is_built_correctly(string $name, string $expected): void
    {
        $this->assertSame($expected, MongolianCase::accusative($name));
    }

    public function test_an_already_inflected_name_is_left_alone(): void
    {
        $this->assertSame('Д.Батцэцэгийг', MongolianCase::accusative('Д.Батцэцэгийг'));
    }

    public function test_only_the_last_word_of_a_phrase_changes(): void
    {
        $this->assertSame(
            'Төрийн захиргааны удирдлагын хэлтсийн',
            MongolianCase::genitive('Төрийн захиргааны удирдлагын хэлтэс'),
        );

        $this->assertSame(
            'Дорноговь аймгийн ЗДТГ-ын',
            MongolianCase::genitive('Дорноговь аймгийн ЗДТГ'),
        );
    }

    public function test_an_already_inflected_word_is_left_alone(): void
    {
        // Давхар залгахгүй.
        $this->assertSame('хэлтсийн', MongolianCase::genitiveWord('хэлтсийн'));
        $this->assertSame('ЗДТГ-ын', MongolianCase::genitiveWord('ЗДТГ-ын'));
    }

    public function test_an_empty_value_stays_empty(): void
    {
        $this->assertSame('', MongolianCase::genitive('   '));
    }
}
