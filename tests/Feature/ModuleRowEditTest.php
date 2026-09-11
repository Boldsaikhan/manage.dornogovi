<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Бүртгэлтэй мөрийг засах.
 */
class ModuleRowEditTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(): TravelAssignment
    {
        return TravelAssignment::create([
            'approver' => 'chief',
            'person_name' => 'Б.Баттулга',
            'position' => 'Мэргэжилтэн',
            'destination' => 'Улаанбаатар',
            'purpose' => 'Сургалт',
            'start_date' => '2026-02-04',
            'end_date' => '2026-02-06',
            'status' => 'approved',
        ]);
    }

    public function test_the_edit_form_is_filled_from_the_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment();

        $values = $this->actingAs($admin)
            ->getJson(route('modules.edit', ['module' => 'assignments', 'id' => $row->id]))
            ->assertOk()
            ->json('values');

        $this->assertSame('Улаанбаатар', $values['destination']);
        $this->assertSame('Сургалт', $values['purpose']);
        // Огноо нь маягтын date талбарт тохирох хэлбэртэй.
        $this->assertSame('2026-02-04', $values['start_date']);
        $this->assertSame('approved', $values['status']);
    }

    public function test_a_row_can_be_saved_after_editing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment();

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.update', ['module' => 'assignments', 'id' => $row->id]), [
                'destination' => 'Дархан',
                'purpose' => 'Хурал',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-03',
                'status' => 'done',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row->refresh();

        $this->assertSame('Дархан', $row->destination);
        $this->assertSame('Хурал', $row->purpose);
        $this->assertSame('done', $row->status);
        // Заагаагүй талбар хэвээр үлдэнэ.
        $this->assertSame('Б.Баттулга', $row->person_name);
        $this->assertSame('chief', $row->approver);
    }

    public function test_a_viewer_without_edit_rights_is_refused(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $row = $this->assignment();

        $this->actingAs($viewer)
            ->getJson(route('modules.edit', ['module' => 'assignments', 'id' => $row->id]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('modules.update', ['module' => 'assignments', 'id' => $row->id]), [
                'destination' => 'Дархан',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-03',
            ])
            ->assertForbidden();

        $this->assertSame('Улаанбаатар', $row->fresh()->destination);
    }
}
