<?php

namespace Tests\Feature;

use App\Models\AnnualLeave;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnnualLeaveTableSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_row_then_fill_cells_inline(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();
        $this->assertSame('baiguullaga', $row->scope);
        $this->assertNull($row->person_name);

        // Хоосон мөрийн «Овог, нэр» баганад бүртгэсэн хэрэглэгчийн нэр
        // орлон гарч ирэхгүй — жинхэнэ хоосон байна.
        $this->actingAs($admin)
            ->get(route('annual-leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.person_name', null));

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), [
                'org_name' => 'Санхүүгийн хэлтэс',
                'work_years' => 5,
                'entitled_days' => 24,
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-24',
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('Санхүүгийн хэлтэс', $row->org_name);
        $this->assertSame(5, $row->work_years);
        $this->assertSame(24, $row->entitled_days);
    }

    public function test_choosing_a_name_fills_the_position(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'Н.Алдарбаяр',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'Санхүүгийн хэлтэс',
        ]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['person_name' => 'Н.Алдарбаяр'])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('Н.Алдарбаяр', $row->person_name);
        $this->assertSame('Мэргэжилтэн', $row->position);
        $this->assertSame('Санхүүгийн хэлтэс', $row->org_name);
    }

    public function test_choosing_a_substitute_fills_their_position_and_phone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'Б.Мэргэжилтэн',
            'position' => 'Ахлах мэргэжилтэн',
            'org_name' => 'Санхүүгийн хэлтэс',
            'mobile_phone' => '99001122',
        ]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['substitute_name' => 'Б.Мэргэжилтэн'])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame('Б.Мэргэжилтэн', $row->substitute_name);
        $this->assertSame('Ахлах мэргэжилтэн', $row->substitute_position);
        $this->assertSame('99001122', $row->substitute_phone);
    }

    public function test_export_downloads_a_file_for_each_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        foreach (['xlsx', 'docx', 'pdf'] as $format) {
            $response = $this->actingAs($admin)
                ->get(route('annual-leaves.export', ['format' => $format, 'scope' => 'baiguullaga']));

            $response->assertOk();
            $this->assertNotEmpty($response->getContent());
        }

        $this->actingAs($admin)
            ->get(route('annual-leaves.export', ['format' => 'xlsx', 'ids' => (string) $row->id]))
            ->assertOk();
    }

    public function test_create_update_and_delete_are_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Админ']);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['person_name' => 'Ц.Мөнхбат'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('annual-leaves.destroy', $row))
            ->assertRedirect();

        $response = $this->actingAs($admin)
            ->get(route('annual-leaves.logs', ['scope' => 'baiguullaga']))
            ->assertOk();

        $rows = $response->json('rows');

        $this->assertCount(3, $rows);
        $this->assertSame('deleted', $rows[0]['action']);
        $this->assertSame('updated', $rows[1]['action']);
        $this->assertSame('created', $rows[2]['action']);
    }

    public function test_the_numbering_runs_from_the_largest_down(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['Нэгдүгээр', 'Хоёрдугаар', 'Гуравдугаар'] as $name) {
            $this->actingAs($admin)->post(route('annual-leaves.store'), [
                'scope' => 'baiguullaga',
            ])->assertRedirect();

            $latest = AnnualLeave::query()->latest('id')->first();
            $this->actingAs($admin)
                ->patch(route('annual-leaves.update', $latest), ['person_name' => $name])
                ->assertRedirect();
        }

        $this->actingAs($admin)
            ->get(route('annual-leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/AnnualLeaves')
                ->has('rows', 3)
                ->where('rows.0.person_name', 'Гуравдугаар'));
    }
}
