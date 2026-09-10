<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\PhoneDirectoryEntry;
use App\Support\TushaalBRegister2026;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026 оны «Б» тушаалын бүртгэлийн импорт.
 */
class TushaalBRegisterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_rows_are_imported_once(): void
    {
        $added = TushaalBRegister2026::fillMissing();

        $this->assertCount(125, $added);
        $this->assertSame(125, Decree::query()->where('kind', 'tushaal_b')->count());

        $first = Decree::query()->where('kind', 'tushaal_b')->where('number', '01')->firstOrFail();
        $this->assertSame('tushaal', $first->category);
        $this->assertSame('Цалин хөлсийг шинэчлэн тогтоох тухай', $first->title);
        $this->assertSame('2026-01-02', $first->issued_on->format('Y-m-d'));
        $this->assertSame('Б/01', $first->numberDisplay());

        $last = Decree::query()->where('kind', 'tushaal_b')->where('number', '125')->firstOrFail();
        $this->assertSame('Д.Цэнгэлмаад дэмжлэг үзүүлэх тухай', $last->title);
        $this->assertSame('Б/125', $last->numberDisplay());

        // Дахин ажиллуулахад хуулбар үүсэхгүй.
        $this->assertSame([], TushaalBRegister2026::fillMissing());
    }

    public function test_existing_rows_are_left_alone(): void
    {
        Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_b',
            'number' => '01',
            'title' => 'Гараар оруулсан',
        ]);

        $added = TushaalBRegister2026::fillMissing();

        $this->assertNotContains('01', $added);
        $this->assertSame(
            'Гараар оруулсан',
            Decree::query()->where('kind', 'tushaal_b')->where('number', '01')->value('title'),
        );
    }

    public function test_the_officer_name_comes_from_the_phone_directory(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Батбаяр Зоригтбаатар',
            'position' => 'Ахлах мэргэжилтэн',
            'mobile_phone' => '85117031',
        ]);

        TushaalBRegister2026::fillMissing();

        $this->assertSame(
            'Б.Зоригтбаатар',
            Decree::query()->where('kind', 'tushaal_b')->where('number', '02')->value('person_name'),
        );
    }

    public function test_the_console_command_fills_the_b_register(): void
    {
        $this->artisan('decrees:fill tushaal-b')
            ->expectsOutputToContain('Б/125')
            ->assertSuccessful();

        $this->assertSame(125, Decree::query()->where('kind', 'tushaal_b')->count());
    }
}
