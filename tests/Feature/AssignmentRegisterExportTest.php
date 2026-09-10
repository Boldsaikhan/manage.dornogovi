<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Томилолтын бүртгэлийг сонгож татах, дугаарлалт ихээсээ бага руу байх.
 */
class AssignmentRegisterExportTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(string $person): TravelAssignment
    {
        return TravelAssignment::create([
            'approver' => 'chief',
            'person_name' => $person,
            'position' => 'Мэргэжилтэн',
            'destination' => 'Улаанбаатар',
            'start_date' => '2026-02-04',
            'end_date' => '2026-02-06',
            'status' => 'approved',
        ]);
    }

    public function test_the_numbering_runs_from_the_largest_down(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assignment('Нэгдүгээр');
        $this->assignment('Хоёрдугаар');
        $this->assignment('Гуравдугаар');

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Хамгийн сүүлд нэмсэн мөр дээрээ, хамгийн том дугаартай.
                ->where('rowNumberStart', 3)
                ->where('rows.0.user_name', 'Гуравдугаар')
                ->where('canExportFile', true)
            );
    }

    public function test_the_table_shows_who_approved_the_assignment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Б.Ганбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'ЗДТГ',
        ]);

        $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.approved_by', 'Б.Ганбат')
                // «Албан тушаал»-ын баруун талд байрлана.
                ->where('columns.1.key', 'user_position')
                ->where('columns.2.key', 'approved_by')
                ->where('columns.2.label', 'Баталсан')
            );
    }

    public function test_only_the_selected_rows_are_downloaded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $keep = $this->assignment('Татах хүн');
        $this->assignment('Татахгүй хүн');

        $response = $this->actingAs($admin)->get(route('modules.export', [
            'module' => 'assignments',
            'scope' => 'chief',
            'format' => 'xlsx',
            'ids' => $keep->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=assignments.xlsx');

        $body = $response->streamedContent();
        $this->assertStringContainsString('Татах хүн', $this->sheetText($body));
        $this->assertStringNotContainsString('Татахгүй хүн', $this->sheetText($body));
    }

    public function test_without_a_selection_the_whole_tab_is_downloaded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assignment('Татах хүн');
        $this->assignment('Бас татах хүн');

        // Өөр табын мөр орохгүй.
        TravelAssignment::create([
            'approver' => 'governor',
            'person_name' => 'Захирагчийн хүн',
            'destination' => 'Сайншанд',
            'status' => 'approved',
        ]);

        $text = $this->sheetText(
            $this->actingAs($admin)
                ->get(route('modules.export', [
                    'module' => 'assignments', 'scope' => 'chief', 'format' => 'xlsx',
                ]))
                ->assertOk()
                ->streamedContent()
        );

        $this->assertStringContainsString('Татах хүн', $text);
        $this->assertStringContainsString('Бас татах хүн', $text);
        $this->assertStringNotContainsString('Захирагчийн хүн', $text);
    }

    public function test_word_and_pdf_are_available_too(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assignment('Татах хүн');

        foreach (['docx', 'pdf'] as $format) {
            $this->actingAs($admin)
                ->get(route('modules.export', [
                    'module' => 'assignments', 'scope' => 'chief', 'format' => $format,
                ]))
                ->assertOk();
        }
    }

    public function test_a_module_without_export_is_refused(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('modules.export', ['module' => 'plans', 'format' => 'xlsx']))
            ->assertNotFound();
    }

    /** Excel файлын доторх текстийг задлан унших. */
    private function sheetText(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
        file_put_contents($path, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Excel файл нээгдсэнгүй.');
        $text = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        @unlink($path);

        return $text;
    }
}
