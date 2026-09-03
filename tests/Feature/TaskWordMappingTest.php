<?php

namespace Tests\Feature;

use App\Models\TaskSource;
use App\Support\TaskDocxParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWordMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_headers_are_matched_to_the_chosen_table_columns(): void
    {
        $headers = ['№', 'Үүрэг чиглэл', 'Хариуцах эзэн', 'Хяналт тавих албан тушаалтан'];

        $mapping = TaskDocxParser::guessMapping($headers, ['text', 'responsible', 'collaborator']);

        $this->assertSame([
            'text' => 1,
            'responsible' => 2,
            'collaborator' => 3,
        ], $mapping);
    }

    public function test_synonyms_and_unmatched_columns_are_handled(): void
    {
        $headers = ['Ажлын чиглэл', 'Арга хэмжээ', 'Хугацаа'];

        $mapping = TaskDocxParser::guessMapping($headers, ['sector', 'measure', 'period', 'note']);

        $this->assertSame(0, $mapping['sector']);
        $this->assertSame(1, $mapping['measure']);
        $this->assertSame(2, $mapping['period']);
        // Word-д «Хэрэгжилт» багана байхгүй — хоосон үлдэнэ.
        $this->assertNull($mapping['note']);
    }

    public function test_without_headers_columns_fall_back_to_order(): void
    {
        $mapping = TaskDocxParser::guessMapping(['№', '', ''], ['text', 'responsible']);

        // № баганыг алгасаад дараалуулна.
        $this->assertSame(['text' => 1, 'responsible' => 2], $mapping);
    }

    public function test_rows_are_built_from_the_mapping(): void
    {
        $rows = [
            ['1', 'Хөрөнгө оруулалтыг эрчимжүүлэх', 'М.Мөнхбат', 'АЗДТГ-ын дарга'],
            ['2', 'Замын ажлыг хурдасгах', 'Ц.Мөнх-Эрдэнэ', ''],
        ];

        $built = app(TaskDocxParser::class)->rowsFromMapping($rows, [
            'text' => 1,
            'responsible' => 2,
            'collaborator' => 3,
        ]);

        $this->assertCount(2, $built);
        $this->assertSame('Хөрөнгө оруулалтыг эрчимжүүлэх', $built[0]['text']);
        $this->assertSame('М.Мөнхбат', $built[0]['responsible']);
        $this->assertSame('АЗДТГ-ын дарга', $built[0]['collaborator']);
        $this->assertNull($built[1]['collaborator']);
    }

    public function test_rows_survive_when_the_section_has_no_text_column(): void
    {
        $rows = [['1', 'Ажлын чиглэл А', '08.01-09.30']];

        $built = app(TaskDocxParser::class)->rowsFromMapping($rows, [
            'sector' => 1,
            'period' => 2,
        ]);

        $this->assertCount(1, $built);
        $this->assertSame('Ажлын чиглэл А', $built[0]['sector']);
        $this->assertSame('08.01-09.30', $built[0]['period']);
    }

    public function test_catalog_labels_all_have_a_guess_path(): void
    {
        $headers = collect(TaskSource::columnCatalog())->pluck('label')->all();

        $mapping = TaskDocxParser::guessMapping($headers, TaskSource::columnKeys());

        foreach (TaskSource::columnKeys() as $key) {
            $this->assertNotNull($mapping[$key], "«{$key}» таарсангүй.");
        }
    }
}
