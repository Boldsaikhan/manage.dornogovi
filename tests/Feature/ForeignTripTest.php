<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ForeignTrip;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Гадаадад зорчих хүсэлт» — бусад HR модулийн адил хоосон мөр нэмээд,
 * нүд нүдээр нь бөглөдөг бүртгэл + тусдаа хэвлэх маягт.
 */
class ForeignTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_shows_the_total_and_scope_tabs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ForeignTrip::create(['scope' => 'agentlag', 'person_name' => 'Б.Гантөмөр']);
        ForeignTrip::create(['scope' => 'sum', 'person_name' => 'Ц.Батсугир']);
        ForeignTrip::create(['scope' => 'baiguullaga', 'person_name' => 'Н.Алдарбаяр']);

        $this->actingAs($admin)
            ->get(route('foreign-trips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('module', 'foreign-trips')
                ->has('scopeTabs', 4)
                ->where('scopeTabs.0.label', 'Нийт')
                ->where('scopeTabs.0.count', 3));
    }

    public function test_a_blank_row_can_be_added_and_filled_in_one_cell_at_a_time(): void
    {
        $department = Department::create(['name' => 'Санхүүгийн хэлтэс', 'code' => 'FIN', 'sort_order' => 1, 'is_active' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'department_id' => $department->id]);

        $this->actingAs($admin)
            ->post(route('modules.store', ['module' => 'foreign-trips']), ['blank' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = ForeignTrip::query()->sole();
        $this->assertNull($row->person_name);
        $this->assertSame($department->id, $row->department_id);

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                'field' => 'person_name',
                'value' => 'Б.Гантөмөр',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                'field' => 'destination_country',
                'value' => 'Солонгос',
            ])
            ->assertRedirect();

        $fresh = $row->fresh();
        $this->assertSame('Б.Гантөмөр', $fresh->person_name);
        $this->assertSame('Солонгос', $fresh->destination_country);
    }

    public function test_the_name_cell_gets_the_phone_directory_as_options(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'org_name' => 'Санхүүгийн хэлтэс',
            'person_name' => 'Б.Гантөмөр',
            'position' => 'Ахлах мэргэжилтэн',
        ]);

        $this->actingAs($admin)
            ->get(route('foreign-trips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('inlineFields.person_name.people', true)
                ->has('formMeta.people', 1)
                ->where('formMeta.people.0.value', 'Б.Гантөмөр'));
    }

    public function test_the_destination_country_is_chosen_from_a_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('foreign-trips.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('inlineFields.destination_country.type', 'select')
                ->where('inlineFields.destination_country.options.Солонгос', 'Солонгос'));

        $row = ForeignTrip::create(['scope' => 'baiguullaga']);

        // Жагсаалтад байхгүй улсыг хүлээж авахгүй.
        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                'field' => 'destination_country',
                'value' => 'Атлантис',
            ])
            ->assertSessionHasErrors('value');

        $this->assertNull($row->fresh()->destination_country);
    }

    public function test_the_companion_count_and_the_cost_take_numbers_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = ForeignTrip::create(['scope' => 'baiguullaga']);

        foreach (['companions' => '3', 'funding_source' => '1500000'] as $field => $value) {
            $this->actingAs($admin)
                ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                    'field' => $field,
                    'value' => $value,
                ])
                ->assertRedirect();

            $this->actingAs($admin)
                ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                    'field' => $field,
                    'value' => 'хоёр хүн',
                ])
                ->assertSessionHasErrors('value');
        }

        $fresh = $row->fresh();
        $this->assertSame('3', $fresh->companions);
        $this->assertSame('1500000', $fresh->funding_source);
    }

    public function test_choosing_a_name_fills_in_the_position_and_the_organisation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'org_name' => 'Санхүүгийн хэлтэс',
            'person_name' => 'Б.Гантөмөр',
            'position' => 'Ахлах мэргэжилтэн',
        ]);

        $row = ForeignTrip::create(['scope' => 'baiguullaga']);

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                'field' => 'person_name',
                'value' => 'Б.Гантөмөр',
            ])
            ->assertRedirect();

        $fresh = $row->fresh();
        $this->assertSame('Ахлах мэргэжилтэн', $fresh->position);
        $this->assertSame('Санхүүгийн хэлтэс', $fresh->org_name);

        // Нэрийг арилгахад хоёулаа арилна.
        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'foreign-trips', 'id' => $row->id]), [
                'field' => 'person_name',
                'value' => '',
            ])
            ->assertRedirect();

        $fresh = $row->fresh();
        $this->assertNull($fresh->position);
        $this->assertNull($fresh->org_name);
    }

    public function test_the_print_form_shows_the_filled_fields_and_blank_dots_for_the_rest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $trip = ForeignTrip::create([
            'scope' => 'baiguullaga',
            'org_name' => 'Санхүүгийн хэлтэс',
            'position' => 'Ахлах мэргэжилтэн',
            'person_name' => 'Б.Гантөмөр',
            'destination_country' => 'Солонгос',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
            'reason' => 'Сургалтад оролцох',
        ]);

        $this->actingAs($admin)
            ->get(route('foreign-trips.print', $trip))
            ->assertOk()
            // «ХҮСЭЛТ ГАРГАСАН» мөрөнд хүсэлт гаргагчийн нэр бичигдэнэ.
            ->assertSee('ХҮСЭЛТ ГАРГАСАН', false)
            ->assertSeeInOrder(['ХҮСЭЛТ ГАРГАСАН', 'Б.Гантөмөр'], false)
            ->assertSee('Гадаад улсад зорчих хүсэлт', false)
            ->assertSee('Санхүүгийн хэлтэс')
            ->assertSee('Ахлах мэргэжилтэн')
            ->assertSee('Б.Гантөмөр')
            ->assertSee('Солонгос')
            ->assertSee('Сургалтад оролцох')
            // Бөглөөгүй талбарууд (жишээ нь хамт зорчих хүн) цэгэн зурастай хоосон үлдэнэ.
            ->assertSee('class="line"', false);
    }

    public function test_a_viewer_without_edit_access_cannot_add_a_row(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);

        \App\Models\UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'foreign-trips',
            'level' => 'view',
        ]);

        $this->actingAs($viewer)
            ->post(route('modules.store', ['module' => 'foreign-trips']), ['blank' => true])
            ->assertForbidden();
    }
}
