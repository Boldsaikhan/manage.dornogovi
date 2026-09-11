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

    public function test_the_approver_is_chosen_from_the_leadership_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Утасны жагсаалтын «Удирдлага» ангилал.
        foreach ([['О.Батжаргал', 'Аймгийн Засаг дарга'], ['Г.Март', 'Засаг даргын орлогч'], ['М.Мөнхбат', 'ЗДТГ-ын дарга']] as $i => [$name, $position]) {
            \App\Models\PhoneDirectoryEntry::create([
                'person_name' => $name,
                'position' => $position,
                'org_name' => 'Аймгийн удирдлага',
                'category' => 'udirdlaga',
                'sort_order' => $i,
            ]);
        }

        // Өөр ангиллын хүн сонголтод орохгүй.
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Б.Мэргэжилтэн',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'ХХҮГ',
            'category' => 'baiguullaga',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];

                $field = collect($props['fields'])->firstWhere('name', 'approved_by');

                $this->assertSame('select', $field['type']);
                $this->assertSame(
                    ['О.Батжаргал', 'Г.Март', 'М.Мөнхбат'],
                    array_keys($field['options']),
                );
                $this->assertSame('О.Батжаргал — Аймгийн Засаг дарга', $field['options']['О.Батжаргал']);
            });
    }

    public function test_the_chosen_approver_is_saved_and_shown(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Г.Март',
            'position' => 'Засаг даргын орлогч',
            'org_name' => 'Аймгийн удирдлага',
            'category' => 'udirdlaga',
        ]);

        $row = $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.update', ['module' => 'assignments', 'id' => $row->id]), [
                'approved_by' => 'Г.Март',
                'destination' => 'Улаанбаатар',
                'start_date' => '2026-02-04',
                'end_date' => '2026-02-06',
            ])
            ->assertRedirect();

        $this->assertSame('Г.Март', $row->fresh()->approved_by);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.approved_by', 'Г.Март'));
    }

    public function test_someone_outside_the_leadership_list_is_refused(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Г.Март',
            'position' => 'Засаг даргын орлогч',
            'org_name' => 'Аймгийн удирдлага',
            'category' => 'udirdlaga',
        ]);

        $row = $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.update', ['module' => 'assignments', 'id' => $row->id]), [
                'approved_by' => 'Хэн нэгэн',
                'destination' => 'Улаанбаатар',
                'start_date' => '2026-02-04',
                'end_date' => '2026-02-06',
            ])
            ->assertSessionHasErrors('approved_by');

        $this->assertNull($row->fresh()->approved_by);
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
