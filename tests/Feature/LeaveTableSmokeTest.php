<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaveTableSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_carry_table_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Б.Батбаяр',
            'slip_number' => '521',
            'signer' => 'О.Батжаргал',
            'type' => 'eeljiin',
            'start_date' => '2026-08-25',
            'days' => 3,
            'reason' => 'гэр бүлийн шалтгаанаар',
        ])->assertRedirect();

        $this->assertSame(1, Leave::query()->count());

        $this->actingAs($admin)
            ->get(route('leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Leaves')
                ->where('rows.0.slip_number', '521')
                ->where('rows.0.person_name', 'Б.Батбаяр')
                ->where('rows.0.days', 3)
                ->where('rows.0.start_date', '2026-08-25')
                ->has('rows.0.end_date')
                ->where('rows.0.type_label', 'Ээлжийн амралтаас'));
    }

    public function test_add_row_then_fill_cells_inline(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $leave = Leave::query()->sole();
        $this->assertNull($leave->person_name);
        $this->assertNull($leave->signer);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'Ц.Мөнхбат'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['start_date' => '2026-09-01', 'days' => 4])
            ->assertRedirect();

        $leave->refresh();
        $this->assertSame('Ц.Мөнхбат', $leave->person_name);
        $this->assertSame('2026-09-04', $leave->end_date->toDateString());
    }

    public function test_export_downloads_a_file_for_each_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Б.Батбаяр',
            'type' => 'eeljiin',
            'start_date' => '2026-08-25',
            'days' => 3,
        ])->assertRedirect();

        $leave = Leave::query()->sole();

        foreach (['xlsx', 'docx', 'pdf'] as $format) {
            $response = $this->actingAs($admin)
                ->get(route('leaves.export', ['format' => $format, 'scope' => 'baiguullaga']));

            $response->assertOk();
            $this->assertNotEmpty($response->getContent());
        }

        $selected = $this->actingAs($admin)
            ->get(route('leaves.export', ['format' => 'xlsx', 'ids' => (string) $leave->id]));

        $selected->assertOk();
    }

    public function test_create_update_and_delete_are_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Админ']);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $leave = Leave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'Ц.Мөнхбат'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('leaves.destroy', $leave))
            ->assertRedirect();

        $response = $this->actingAs($admin)
            ->get(route('leaves.logs', ['scope' => 'baiguullaga']))
            ->assertOk();

        $rows = $response->json('rows');

        $this->assertCount(3, $rows);
        $this->assertSame('deleted', $rows[0]['action']);
        $this->assertSame('updated', $rows[1]['action']);
        $this->assertSame('created', $rows[2]['action']);
        $this->assertSame('Админ', $rows[0]['user']);
        $this->assertSame('Ц.Мөнхбат', $rows[1]['changes']['Албан хаагч']['to']);
    }

    public function test_signer_options_are_limited_to_leader_positions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Г.Март',
            'position' => 'Засаг даргын орлогч',
            'org_name' => 'АЗДТГ',
        ]);
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Н.Алдарбаяр',
            'position' => 'Санхүүгийн хэлтсийн дарга',
            'org_name' => 'Санхүүгийн хэлтэс',
        ]);
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'Тамгын газрын дарга',
            'org_name' => 'АЗДТГ',
        ]);
        // Жирийн мэргэжилтэн сонголтод орохгүй.
        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Б.Мэргэжилтэн',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'Санхүүгийн хэлтэс',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('signers'));

        $signers = $response->viewData('page')['props']['signers'];

        $this->assertSame([
            'О.Батжаргал' => 'О.Батжаргал — Аймгийн Засаг дарга',
            'Г.Март' => 'Г.Март — Засаг даргын орлогч',
            'Н.Алдарбаяр' => 'Н.Алдарбаяр — Санхүүгийн хэлтсийн дарга',
            'М.Мөнхбат' => 'М.Мөнхбат — Тамгын газрын дарга',
        ], $signers);
    }

    public function test_the_slip_shows_the_signers_own_title(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Н.Алдарбаяр',
            'position' => 'Санхүүгийн хэлтсийн дарга',
            'org_name' => 'Санхүүгийн хэлтэс',
        ]);

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'signer' => 'Н.Алдарбаяр',
        ])->assertRedirect();

        $leave = Leave::query()->sole();

        $this->actingAs($admin)
            ->get(route('leaves.slip', $leave))
            ->assertOk()
            ->assertSee('САНХҮҮГИЙН ХЭЛТСИЙН ДАРГА')
            ->assertSee('Н.Алдарбаяр');
    }
}
