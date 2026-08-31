<?php

namespace Tests\Feature;

use App\Support\PhoneDirectoryDocxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PhoneDirectoryExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_export_uses_arial_11_and_single_and_a_half_spacing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phones').'.docx';

        app(PhoneDirectoryDocxWriter::class)->write([
            [
                'org_name' => 'Тест хэлтэс',
                'rows' => [[
                    'person_name' => 'Б.Болд',
                    'position' => 'Байгаль орчны бодлого хариуцсан мэргэжилтэн',
                    'office_phone' => '70521111',
                    'mobile_phone' => '99112233',
                ]],
            ],
        ], $path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $styles = $zip->getFromName('word/styles.xml');
        $document = $zip->getFromName('word/document.xml');

        $zip->close();
        @unlink($path);

        // Arial
        $this->assertStringContainsString('w:ascii="Arial"', $styles);

        // 11pt = 22 half-points
        $this->assertStringContainsString('<w:sz w:val="22"/>', $styles);

        // 1.15 мөр = 276 twip (240 = 1 мөр), 1–1.5-ийн дунд.
        $this->assertStringContainsString('w:line="276"', $styles);
        $this->assertStringContainsString('w:lineRule="auto"', $styles);

        // Мөр хуудас хооронд тасрахгүй.
        $this->assertStringContainsString('<w:cantSplit/>', $document);
    }
}
