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
                // «Төлөв»-ийн баруун талд, хамгийн сүүлд байрлана.
                ->where('columns.7.key', 'status')
                ->where('columns.8.key', 'approved_by')
                ->where('columns.8.label', 'Баталсан')
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

    public function test_the_approver_can_be_changed_straight_from_the_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'Аймгийн удирдлага',
            'category' => 'udirdlaga',
        ]);

        $row = $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                'field' => 'approved_by',
                'value' => 'М.Мөнхбат',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('М.Мөнхбат', $row->fresh()->approved_by);

        // Хоосон болгож болно.
        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                'field' => 'approved_by',
                'value' => '',
            ])
            ->assertRedirect();

        $this->assertNull($row->fresh()->approved_by);
    }

    public function test_only_whitelisted_fields_can_be_changed_inline(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment('Томилолттой хүн');

        // Хүснэгтэд багана болж гараагүй талбарыг ингэж засахгүй.
        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                'field' => 'report',
                'value' => 'Оролдлого',
            ])
            ->assertStatus(422);

        $this->assertNull($row->fresh()->report);
    }

    public function test_table_columns_can_be_filled_in_place(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment('Томилолттой хүн');

        foreach ([
            ['person_name', 'Н.Гарамжав'],
            ['position', 'Байцаагч'],
            ['destination', 'Дархан'],
            ['purpose', 'Сургалт'],
            ['order_number', 'А/12'],
        ] as [$field, $value]) {
            $this->actingAs($admin)
                ->from(route('assignments.index', ['scope' => 'chief']))
                ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                    'field' => $field,
                    'value' => $value,
                ])
                ->assertRedirect();
        }

        $row->refresh();

        $this->assertSame('Н.Гарамжав', $row->person_name);
        $this->assertSame('Дархан', $row->destination);
        $this->assertSame('А/12', $row->order_number);
    }

    public function test_choosing_a_name_fills_the_position(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Намсрайн Алдарбаяр',
            'position' => 'ИТХ-ын дарга',
            'org_name' => 'Аймгийн ИТХ',
        ]);

        $row = $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                'field' => 'person_name',
                'value' => 'Н.Алдарбаяр',
            ])
            ->assertRedirect();

        $row->refresh();

        $this->assertSame('Н.Алдарбаяр', $row->person_name);
        // Албан тушаал нь дагаж бөглөгдөнө.
        $this->assertSame('ИТХ-ын дарга', $row->position);
    }

    public function test_a_blank_row_can_be_added_to_the_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.store', ['module' => 'assignments']), [
                'blank' => true,
                'approver' => 'chief',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = TravelAssignment::query()->latest('id')->first();

        // Заавал бөглөх талбаруудыг шаардахгүй.
        $this->assertSame('chief', $row->approver);
        $this->assertNull($row->destination);
    }

    public function test_the_leadership_list_falls_back_to_the_position(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // «Удирдлага» ангилал тэмдэглэгдээгүй байсан ч олдоно.
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
            'category' => 'baiguullaga',
        ]);
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Б.Мэргэжилтэн',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'АЗДТГ',
            'category' => 'baiguullaga',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];

                $options = $props['inlineFields']['approved_by']['options'];

                $this->assertSame(['О.Батжаргал'], array_keys($options));
                // Хүснэгтийн нүдэнд зөвхөн нэр — албан тушаалгүй.
                $this->assertSame('О.Батжаргал', $options['О.Батжаргал']);
                $this->assertSame('approved_by', $props['inlineFields']['approved_by']['field']);
            });
    }

    public function test_the_status_is_shown_in_mongolian(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assignment('Томилолттой хүн');

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // «approved» биш, «Зөвшөөрсөн».
                ->where('rows.0.status', 'Зөвшөөрсөн'));
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

    public function test_the_word_file_really_holds_the_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->assignment('Татах хүн');

        $binary = $this->actingAs($admin)
            ->get(route('modules.export', [
                'module' => 'assignments', 'scope' => 'chief', 'format' => 'docx',
            ]))
            ->assertOk()
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'docx_').'.docx';
        file_put_contents($path, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Word файл нээгдсэнгүй.');

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        // Мөрийн нүднүүд байхгүй бол Word файлыг эвдэрсэн гэж үзэж нээхгүй.
        $this->assertStringContainsString('Татах хүн', $xml);
        $this->assertGreaterThan(
            count(config('module_resources.assignments.columns')),
            substr_count($xml, '<w:tc>'),
            'Толгойноос гадна өгөгдлийн нүд ч байх ёстой.',
        );
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
