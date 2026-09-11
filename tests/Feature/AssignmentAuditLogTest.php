<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Томилолтын бүртгэлийн өөрчлөлтийн түүх.
 */
class AssignmentAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(): TravelAssignment
    {
        return TravelAssignment::create([
            'approver' => 'chief',
            'person_name' => 'Б.Баттулга',
            'destination' => 'Улаанбаатар',
            'start_date' => '2026-02-04',
            'end_date' => '2026-02-06',
            'status' => 'approved',
        ]);
    }

    public function test_an_edit_is_written_into_the_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Б.Болдсайхан']);
        $row = $this->assignment();

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.update', ['module' => 'assignments', 'id' => $row->id]), [
                'destination' => 'Дархан',
                'start_date' => '2026-02-04',
                'end_date' => '2026-02-06',
                'status' => 'done',
            ]);

        $logs = $this->actingAs($admin)
            ->getJson(route('modules.logs', ['module' => 'assignments', 'scope' => 'chief']))
            ->assertOk()
            ->json('rows');

        $this->assertCount(1, $logs);
        $this->assertSame('Засварласан', $logs[0]['action_label']);
        $this->assertSame('Б.Баттулга', $logs[0]['label']);
        $this->assertSame('Б.Болдсайхан', $logs[0]['user']);

        // Хуучин, шинэ утга хоёулаа харагдана.
        $this->assertSame(
            ['from' => 'Улаанбаатар', 'to' => 'Дархан'],
            $logs[0]['changes']['Очих газар'],
        );
    }

    public function test_an_inline_change_and_a_deletion_are_written_too(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'Аймгийн удирдлага',
            'category' => 'udirdlaga',
        ]);

        $row = $this->assignment();

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.field', ['module' => 'assignments', 'id' => $row->id]), [
                'field' => 'approved_by',
                'value' => 'М.Мөнхбат',
            ]);

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->delete(route('modules.destroy', ['module' => 'assignments', 'id' => $row->id]));

        $logs = $this->actingAs($admin)
            ->getJson(route('modules.logs', ['module' => 'assignments', 'scope' => 'chief']))
            ->json('rows');

        // Хамгийн сүүлийнх нь эхэнд.
        $this->assertSame('Устгасан', $logs[0]['action_label']);
        $this->assertSame('Засварласан', $logs[1]['action_label']);
        $this->assertSame('М.Мөнхбат', $logs[1]['changes']['Баталсан']['to']);
    }

    public function test_the_history_is_kept_per_section(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->assignment();

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->delete(route('modules.destroy', ['module' => 'assignments', 'id' => $row->id]));

        // Өөр табын түүхэнд орохгүй.
        $this->assertCount(
            0,
            $this->actingAs($admin)
                ->getJson(route('modules.logs', ['module' => 'assignments', 'scope' => 'governor']))
                ->json('rows'),
        );

        $this->assertCount(
            1,
            $this->actingAs($admin)
                ->getJson(route('modules.logs', ['module' => 'assignments', 'scope' => 'all']))
                ->json('rows'),
        );
    }

    public function test_the_page_offers_the_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('hasAuditLog', true));
    }
}
