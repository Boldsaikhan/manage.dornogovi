<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Томилолтын бүртгэлийн хүснэгт цаасан маягтын толгойтой тохирох эсэх.
 */
class AssignmentRegisterColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_matches_the_paper_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];
                $labels = collect($props['columns'])->pluck('label')->all();

                $this->assertSame('Д/д', $props['rowNumberLabel']);
                // Овог нэр нь хоёр мөр болж хуваагдахгүй.
                $this->assertTrue($props['columns'][0]['single_line']);
                $this->assertSame(
                    ['Овог нэр', 'Албан тушаал', 'Баталсан', 'Хаана', 'Ямар ажлаар', 'Хэзээнээс', 'Хэд хоног'],
                    array_slice($labels, 0, 7),
                );
            });
    }

    public function test_the_position_and_day_count_are_filled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $staff = User::factory()->create([
            'name' => 'Б.Болд',
            'position' => 'Ахлах мэргэжилтэн',
        ]);

        TravelAssignment::create([
            'user_id' => $staff->id,
            'approver' => 'governor',
            'destination' => 'Улаанбаатар',
            'purpose' => 'Сургалтад оролцох',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.user_name', 'Б.Болд')
                ->where('rows.0.user_position', 'Ахлах мэргэжилтэн')
                ->where('rows.0.destination', 'Улаанбаатар')
                ->where('rows.0.purpose', 'Сургалтад оролцох')
                ->where('rows.0.start_date', '2026-09-10')
                // Эхлэх, дуусах өдрийг оролцуулан 3 хоног.
                ->where('rows.0.day_count', '3'));
    }

    public function test_a_single_day_trip_counts_as_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create();

        TravelAssignment::create([
            'user_id' => $staff->id,
            'approver' => 'governor',
            'destination' => 'Сайншанд',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.day_count', '1'));
    }
}
