<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use App\Support\DecreeRegisterImporter;
use App\Support\XlsxTableWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Захирамж, тушаалын бүртгэлийг Excel файлаас оруулах.
 */
class DecreeRegisterImportTest extends TestCase
{
    use RefreshDatabase;

    /** Цаасан бүртгэлтэй адил толгойтой Excel файл бэлдэнэ. */
    private function excel(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'reg_').'.xlsx';

        app(XlsxTableWriter::class)->write(
            $path,
            'А ЗАХИРАМЖИЙН БҮРТГЭЛ',
            ['Дугаар', 'Огноо', 'Захирамжийн тэргүү', 'Хуудасны тоо', 'Баримт бичгийн нэр', 'Хуудасны тоо', 'Боловсруулсан албан тушаалтан'],
            $rows,
        );

        return new UploadedFile($path, 'zahiramj.xlsx', null, null, true);
    }

    public function test_the_preview_maps_the_columns_and_parses_the_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $file = $this->excel([
            ['1', '2', '3', '4', '5', '6', '7'],
            ['А/01', '2026.01.02', 'Ажлын хэсэг байгуулах тухай', '2', 'Арын бичилт', '1', 'Х.Батмөнх'],
            ['А/02', '2026.01.09', 'Зардал гаргах тухай', '1', '', '', 'Б.Зоригтбаатар'],
        ]);

        $response = $this->actingAs($admin)
            ->post(route('decrees.import.preview'), ['file' => $file, 'tab' => 'zahiramj_a'])
            ->assertOk();

        $data = $response->json();

        $this->assertSame(2, $data['total']);
        $this->assertSame(0, $data['mapping']['number']);
        $this->assertSame(1, $data['mapping']['issued_on']);
        $this->assertSame(2, $data['mapping']['title']);
        $this->assertSame(6, $data['mapping']['person_name']);

        $first = $data['entries'][0];
        $this->assertSame('01', $first['number']);
        $this->assertSame('2026-01-02', $first['issued_on']);
        $this->assertSame('Ажлын хэсэг байгуулах тухай', $first['title']);
        $this->assertSame(2, $first['page_count']);
        $this->assertSame('Х.Батмөнх', $first['person_name']);

        // Урьдчилан харах нь юу ч хадгалахгүй.
        $this->assertSame(0, Decree::query()->count());
    }

    public function test_the_rows_are_stored_into_the_active_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [
                    ['number' => '01', 'issued_on' => '2026-01-02', 'title' => 'Нэгдүгээр', 'page_count' => 1, 'person_name' => 'Х.Батмөнх'],
                    ['number' => '02', 'issued_on' => '2026-01-09', 'title' => 'Хоёрдугаар'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Decree::query()->where('kind', 'zahiramj_a')->count());

        $row = Decree::query()->where('kind', 'zahiramj_a')->where('number', '01')->firstOrFail();
        $this->assertSame('zahiramj', $row->category);
        $this->assertSame('Нэгдүгээр', $row->title);
        $this->assertSame('А/01', $row->numberDisplay());
    }

    public function test_existing_numbers_are_skipped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Гараар оруулсан',
        ]);

        $this->actingAs($admin)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [
                    ['number' => '01', 'title' => 'Файлаас'],
                    ['number' => '02', 'title' => 'Шинэ мөр'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, Decree::query()->where('kind', 'zahiramj_a')->count());
        $this->assertSame(
            'Гараар оруулсан',
            Decree::query()->where('kind', 'zahiramj_a')->where('number', '01')->value('title'),
        );
    }

    public function test_dates_and_numbers_are_understood_in_several_shapes(): void
    {
        $importer = app(DecreeRegisterImporter::class);

        $this->assertSame('2026-01-02', $importer->date('2026.01.02'));
        $this->assertSame('2026-01-02', $importer->date('2026-01-02'));
        $this->assertSame('2026-01-02', $importer->date('02/01/2026'));
        // Excel-ийн серийн дугаар.
        $this->assertSame('2026-01-02', $importer->date('46024'));
        $this->assertNull($importer->date(''));

        $this->assertSame('01', $importer->number('А/01'));
        $this->assertSame('07', $importer->number('7'));
        $this->assertSame('125', $importer->number('Б/125'));
        $this->assertNull($importer->number('—'));
    }

    public function test_a_user_without_edit_rights_cannot_import(): void
    {
        $user = User::factory()->create();

        \App\Models\UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'decrees',
            'level' => 'view',
        ]);

        $this->actingAs($user)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [['number' => '01', 'title' => 'Оролдлого']],
            ])
            ->assertForbidden();

        $this->assertSame(0, Decree::query()->count());
    }
}
