<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\PhoneDirectoryEntry;
use App\Support\ZahiramjBRegister2026;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026 оны «Б» захирамжийн бүртгэлийн импорт.
 */
class ZahiramjBRegisterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_rows_are_imported_once(): void
    {
        $added = ZahiramjBRegister2026::fillMissing();

        $this->assertCount(39, $added);
        $this->assertSame(39, Decree::query()->where('kind', 'zahiramj_b')->count());

        $row = Decree::query()->where('kind', 'zahiramj_b')->where('number', '03')->firstOrFail();
        $this->assertSame('zahiramj', $row->category);
        $this->assertSame('Н.Отгонбаярыг төрийн албанаас түр чөлөөлөх тухай', $row->title);
        $this->assertSame('2026-01-26', $row->issued_on->format('Y-m-d'));
        $this->assertSame('Б/03', $row->numberDisplay());

        // Хавсралттай мөр.
        $withAttachment = Decree::query()->where('kind', 'zahiramj_b')->where('number', '18')->firstOrFail();
        $this->assertSame('Илүү цагийн нэмэгдлийн тооцоо', $withAttachment->attachment_name);
        $this->assertSame(1, $withAttachment->attachment_pages);

        // Огноо, гарчиггүй мөр ч бүртгэгдэнэ (дугаар нь эзэлсэн).
        $empty = Decree::query()->where('kind', 'zahiramj_b')->where('number', '01')->firstOrFail();
        $this->assertSame('', $empty->title);
        $this->assertNull($empty->issued_on);

        $this->assertSame([], ZahiramjBRegister2026::fillMissing());
    }

    public function test_existing_rows_are_left_alone(): void
    {
        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_b',
            'number' => '05',
            'title' => 'Гараар оруулсан',
        ]);

        $added = ZahiramjBRegister2026::fillMissing();

        $this->assertNotContains('05', $added);
        $this->assertSame(
            'Гараар оруулсан',
            Decree::query()->where('kind', 'zahiramj_b')->where('number', '05')->value('title'),
        );
    }

    public function test_the_officer_name_comes_from_the_phone_directory(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Батбаяр Зоригтбаатар',
            'mobile_phone' => '85117031',
        ]);

        ZahiramjBRegister2026::fillMissing();

        $this->assertSame(
            'Б.Зоригтбаатар',
            Decree::query()->where('kind', 'zahiramj_b')->where('number', '03')->value('person_name'),
        );
    }

    public function test_the_command_can_fill_every_register(): void
    {
        $this->artisan('decrees:fill all')
            ->expectsOutputToContain('zahiramj-b')
            ->assertSuccessful();

        $this->assertSame(39, Decree::query()->where('kind', 'zahiramj_b')->count());
        $this->assertSame(125, Decree::query()->where('kind', 'tushaal_b')->count());
        $this->assertSame(74, Decree::query()->where('kind', 'tushaal_a')->count());
    }
}
