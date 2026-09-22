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
                'work_years' => 5,
                'entitled_days' => 24,
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame(5, $row->work_years);
        $this->assertSame(24, $row->entitled_days);
    }

    public function test_the_signer_is_restricted_to_leadership_options(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);
        PhoneDirectoryEntry::create([
            'person_name' => 'Б.Мэргэжилтэн',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'Санхүүгийн хэлтэс',
        ]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['signer' => 'О.Батжаргал'])
            ->assertRedirect();

        $this->assertSame('О.Батжаргал', $row->fresh()->signer);

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['signer' => 'Б.Мэргэжилтэн'])
            ->assertSessionHasErrors('signer');

        $this->assertSame('О.Батжаргал', $row->fresh()->signer);

        $response = $this->actingAs($admin)
            ->get(route('annual-leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('signers'));

        $signers = $response->viewData('page')['props']['signers'];
        $this->assertArrayHasKey('О.Батжаргал', $signers);
        $this->assertArrayNotHasKey('Б.Мэргэжилтэн', $signers);
    }

    public function test_rows_carry_the_registration_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-22 10:00:00');

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        \Illuminate\Support\Carbon::setTestNow();

        $this->actingAs($admin)
            ->get(route('annual-leaves.index', ['scope' => 'baiguullaga']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.registered_on', '2026-09-22'));
    }

    public function test_entitled_days_is_computed_from_work_years(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        foreach ([
            [0, 15], [5, 15], [6, 18], [10, 18], [11, 20], [15, 20],
            [16, 22], [20, 22], [21, 24], [25, 24], [26, 26], [31, 26], [32, 29], [50, 29],
        ] as [$years, $expectedDays]) {
            $this->actingAs($admin)
                ->patch(route('annual-leaves.update', $row), ['work_years' => $years])
                ->assertRedirect();

            $this->assertSame($expectedDays, $row->fresh()->entitled_days, "жил={$years}");
        }
    }

    public function test_entitled_days_can_still_be_overridden_by_hand(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['work_years' => 3])
            ->assertRedirect();
        $this->assertSame(15, $row->fresh()->entitled_days);

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['entitled_days' => 20])
            ->assertRedirect();
        $this->assertSame(20, $row->fresh()->entitled_days);
    }

    public function test_org_name_and_position_cannot_be_typed_directly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), [
                'org_name' => 'Гараар бичсэн байгууллага',
                'position' => 'Гараар бичсэн тушаал',
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertNull($row->org_name);
        $this->assertNull($row->position);
    }

    public function test_the_end_date_is_computed_from_start_date_and_entitled_days(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('annual-leaves.store'), [
            'scope' => 'baiguullaga',
        ])->assertRedirect();

        $row = AnnualLeave::query()->sole();

        // Эхлээд олгох хоногийг бөглөнө, дараа нь эхлэх огноог сонгоно —
        // алийг нь ч эхлээд бөглөсөн дуусах огноог зөв бодох ёстой.
        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['entitled_days' => 15])
            ->assertRedirect();

        $this->assertNull($row->fresh()->end_date);

        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['start_date' => '2026-08-31'])
            ->assertRedirect();

        $this->assertSame('2026-09-14', $row->fresh()->end_date->toDateString());

        // Олгох хоногийг өөрчилвөл дуусах огноо дахин бодогдоно.
        $this->actingAs($admin)
            ->patch(route('annual-leaves.update', $row), ['entitled_days' => 10])
            ->assertRedirect();

        $this->assertSame('2026-09-09', $row->fresh()->end_date->toDateString());
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
