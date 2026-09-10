<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use App\Support\AssignmentRegisterImporter;
use App\Support\XlsxTableWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Томилолтын бүртгэлийг Excel файлаас оруулах.
 */
class AssignmentRegisterImportTest extends TestCase
{
    use RefreshDatabase;

    private function excel(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'assign_').'.xlsx';

        app(XlsxTableWriter::class)->write(
            $path,
            'ТОМИЛОЛТЫН БҮРТГЭЛ',
            ['Д/д', 'Овог нэр', 'Албан тушаал', 'Хаана', 'Ямар ажлаар', 'Хэзээнээс', 'Хэд хоног'],
            $rows,
        );

        return new UploadedFile($path, 'tomilolt.xlsx', null, null, true);
    }

    public function test_the_preview_maps_the_columns_and_understands_the_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $file = $this->excel([
            ['1', 'Н.Гарамжав', 'Эрчим хүчний хяналтын улсын байцаагч', 'Замын-Үүд сум', 'албан ажил', '1.13', ''],
            ['21', 'Б.Баттулга', 'СЗХХ-н байцаагч', 'Улаанбаатар', 'албан ажил', '2.04', '3'],
        ]);

        $data = $this->actingAs($admin)
            ->post(route('modules.import.preview', ['module' => 'assignments']), ['file' => $file])
            ->assertOk()
            ->json();

        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $data['mapping']['person_name']);
        $this->assertSame(5, $data['mapping']['start_date']);
        $this->assertSame(6, $data['mapping']['days']);

        $first = $data['entries'][0];
        $this->assertSame('Н.Гарамжав', $first['person_name']);
        $this->assertSame('Замын-Үүд сум', $first['destination']);
        // «1.13» → 2026 оны 1 сарын 13.
        $this->assertSame('2026-01-13', $first['start_date']);

        $second = $data['entries'][1];
        $this->assertSame('2026-02-04', $second['start_date']);
        $this->assertSame(3, $second['days']);
        // Эхлэх өдрийг оролцуулж 3 хоног.
        $this->assertSame('2026-02-06', $second['end_date']);

        // Урьдчилан харах нь юу ч хадгалахгүй.
        $this->assertSame(0, TravelAssignment::query()->count());
    }

    public function test_rows_are_stored_into_the_selected_section(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['name' => 'Б.Баттулга']);

        $this->actingAs($admin)
            ->post(route('modules.import.store', ['module' => 'assignments']), [
                'scope' => 'chief',
                'entries' => [
                    [
                        'person_name' => 'Б.Баттулга',
                        'position' => 'СЗХХ-н байцаагч',
                        'destination' => 'Улаанбаатар',
                        'purpose' => 'албан ажил',
                        'start_date' => '2026-02-04',
                        'end_date' => '2026-02-06',
                    ],
                    [
                        'person_name' => 'Системд байхгүй хүн',
                        'destination' => 'Айраг сум',
                        'start_date' => '2026-03-05',
                        'end_date' => '2026-03-05',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, TravelAssignment::query()->count());

        $row = TravelAssignment::query()->where('person_name', 'Б.Баттулга')->firstOrFail();
        $this->assertSame('chief', $row->approver);
        $this->assertSame($staff->id, $row->user_id, 'Нэрээр нь бүртгэлтэй хүнтэй холбогдоно.');
        $this->assertSame('СЗХХ-н байцаагч', $row->position);

        // Системд бүртгэлгүй хүн ч бүртгэлд орно.
        $guest = TravelAssignment::query()->where('person_name', 'Системд байхгүй хүн')->firstOrFail();
        $this->assertNull($guest->user_id);
    }

    public function test_duplicate_rows_are_skipped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $entry = [
            'person_name' => 'Ч.Одонбаатар',
            'destination' => 'Улаанбаатар',
            'start_date' => '2026-01-14',
            'end_date' => '2026-01-17',
        ];

        $payload = ['scope' => 'chief', 'entries' => [$entry]];

        $this->actingAs($admin)->post(route('modules.import.store', ['module' => 'assignments']), $payload);
        $this->actingAs($admin)->post(route('modules.import.store', ['module' => 'assignments']), $payload);

        $this->assertSame(1, TravelAssignment::query()->count());
    }

    public function test_the_register_shows_the_imported_name_and_position(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        TravelAssignment::create([
            'approver' => 'chief',
            'person_name' => 'Ч.Одонбаатар',
            'position' => 'ЗД-н жолооч',
            'destination' => 'Улаанбаатар',
            'start_date' => '2026-01-14',
            'end_date' => '2026-01-17',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('rows.0.user_name', 'Ч.Одонбаатар')
                ->where('rows.0.user_position', 'ЗД-н жолооч')
                ->where('rows.0.day_count', '4'));
    }

    public function test_dates_are_read_in_several_shapes(): void
    {
        $importer = app(AssignmentRegisterImporter::class);

        $this->assertSame('2026-01-13', $importer->date('1.13'));
        $this->assertSame('2026-05-21', $importer->date('05.21'));
        $this->assertSame('2026-02-10', $importer->date('2.10.'));
        $this->assertSame('2026-09-07', $importer->date('9.07'));
        $this->assertSame('2026-04-01', $importer->date('04.01.'));
        $this->assertNull($importer->date(''));

        // Excel дээр «1.13» нь бутархай тоо болж хадгалагддаг.
        $this->assertSame('2026-01-13', $importer->date('1.1299999999999999'));
        $this->assertSame('2026-01-09', $importer->date('1.09'));
        $this->assertSame('2026-05-21', $importer->date('5.2100000000000002'));
        // Жинхэнэ огнооны нүд — серийн дугаар.
        $this->assertSame('2026-01-13', $importer->date('46035'));
    }

    public function test_a_row_without_a_date_is_still_imported(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.import.store', ['module' => 'assignments']), [
                'scope' => 'chief',
                'entries' => [
                    ['person_name' => 'Огноогүй хүн', 'destination' => 'Улаанбаатар'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = TravelAssignment::query()->where('person_name', 'Огноогүй хүн')->firstOrFail();

        $this->assertNull($row->start_date);
    }
}
