<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ForeignTrip;
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
