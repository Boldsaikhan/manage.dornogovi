<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Support\PhoneDirectoryDocxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PhoneDirectoryExportTest extends TestCase
{
    use RefreshDatabase;

    /** .docx-ийн үндсэн XML-ийг задалж буцаана. */
    private function documentXml($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pd').'.docx';
        file_put_contents($path, $response->getContent());

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    public function test_only_selected_rows_are_exported(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $keep = PhoneDirectoryEntry::create([
            'org_name' => 'А хэлтэс', 'org_order' => 1, 'sort_order' => 1,
            'person_name' => 'Б.Сонгосон', 'position' => 'Засаг даргын орлогч',
        ]);

        PhoneDirectoryEntry::create([
            'org_name' => 'А хэлтэс', 'org_order' => 1, 'sort_order' => 2,
            'person_name' => 'Д.Сонгоогүй', 'position' => 'Мэргэжилтэн',
        ]);

        $response = $this->actingAs($user)
            ->get(route('phone-directory.export', ['ids' => (string) $keep->id]));

        $response->assertOk();

        $xml = $this->documentXml($response);

        $this->assertStringContainsString('Б.Сонгосон', $xml);
        $this->assertStringNotContainsString('Д.Сонгоогүй', $xml);
        // Гарчиг нь сонгосон болохыг заана.
        $this->assertStringContainsString('сонгосон', $xml);
    }

    public function test_export_without_selection_includes_everyone(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        foreach (['Нэгдүгээр', 'Хоёрдугаар'] as $i => $name) {
            PhoneDirectoryEntry::create([
                'org_name' => 'А хэлтэс', 'org_order' => 1, 'sort_order' => $i + 1,
                'person_name' => $name, 'position' => 'Мэргэжилтэн',
            ]);
        }

        $response = $this->actingAs($user)->get(route('phone-directory.export'));
        $response->assertOk();

        $xml = $this->documentXml($response);

        $this->assertStringContainsString('Нэгдүгээр', $xml);
        $this->assertStringContainsString('Хоёрдугаар', $xml);
    }

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
