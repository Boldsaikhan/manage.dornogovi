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

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Б.Батбаяр',
            'slip_number' => '521',
            'signer' => 'acting',
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
        $this->assertSame('acting', $leave->signer);

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
}
