<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\PhoneDirectoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026 оны «А» тушаалын бүртгэлийн импорт.
 */
class TushaalARegisterImportTest extends TestCase
{
    use RefreshDatabase;

    private function runImport(): void
    {
        $migration = require database_path(
            'migrations/2026_09_10_090000_import_tushaal_a_register_2026.php'
        );

        $migration->import();
    }

    public function test_the_register_rows_are_imported_once(): void
    {
        $this->runImport();

        $rows = Decree::query()->where('kind', 'tushaal_a')->get();
        $this->assertCount(74, $rows);

        $first = $rows->firstWhere('number', '01');
        $this->assertSame('tushaal', $first->category);
        $this->assertSame('Ажил хүлээлцүүлэх ажлын хэсэг томилох тухай', $first->title);
        $this->assertSame('2026-01-07', $first->issued_on->format('Y-m-d'));
        $this->assertSame(2, $first->page_count);
        $this->assertSame('Арын бичилт', $first->attachment_name);
        $this->assertSame('А/01', $first->numberDisplay());

        // Дахин ажиллуулахад хуулбар үүсэхгүй.
        $this->runImport();
        $this->assertSame(74, Decree::query()->where('kind', 'tushaal_a')->count());
    }

    public function test_the_officer_name_comes_from_the_phone_directory(): void
    {
        // Жагсаалтад бүтэн нэрээр бүртгэлтэй — богино хэлбэрээр нь тааруулна.
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Цэрэн Энхтунгалаг',
            'position' => 'Мэргэжилтэн',
            'mobile_phone' => '88591973',
        ]);

        // Цаасан дээр «Л.Оюунсүрэн» — жагсаалтад «Оюунсурэн» (ү/у зөрүүтэй).
        PhoneDirectoryEntry::create([
            'org_name' => 'Санхүүгийн хэлтэс',
            'person_name' => 'Лхагва Оюунсурэн',
            'position' => 'Мэргэжилтэн',
            'mobile_phone' => '99112255',
        ]);

        $this->runImport();

        $this->assertSame(
            'Ц.Энхтунгалаг',
            Decree::query()->where('kind', 'tushaal_a')->where('number', '61')->value('person_name'),
        );

        // Үсгийн зөрүүтэй ч жагсаалтын бичлэгээр бичигдэнэ.
        $this->assertSame(
            'Л.Оюунсурэн',
            Decree::query()->where('kind', 'tushaal_a')->where('number', '05')->value('person_name'),
        );
    }

    public function test_a_name_missing_from_the_directory_is_kept_as_written(): void
    {
        $this->runImport();

        $this->assertSame(
            'Б.Зоритбаатар',
            Decree::query()->where('kind', 'tushaal_a')->where('number', '01')->value('person_name'),
        );

        // Нэргүй мөрүүд хоосон үлдэнэ.
        $this->assertNull(
            Decree::query()->where('kind', 'tushaal_a')->where('number', '32')->value('person_name'),
        );
    }

    public function test_the_import_can_be_rolled_back(): void
    {
        $this->runImport();

        $migration = require database_path(
            'migrations/2026_09_10_090000_import_tushaal_a_register_2026.php'
        );

        $migration->down();

        $this->assertSame(0, Decree::query()->where('kind', 'tushaal_a')->count());
    }
}
