<?php

namespace Tests\Feature;

use App\Models\AnnualLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualLeaveRecomputeEndDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recomputes_stale_calendar_day_end_dates_as_working_days(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Хуучин (хуанлийн өдрөөр бодсон) утга шууд бичигдсэн гэж дуурайна.
        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
            'entitled_days' => 20,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-22',
        ]);

        // Огноо хоёрын хамаарал байхгүй мөрт хүрэхгүй.
        $untouched = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Хоосон',
        ]);

        $this->artisan('annual-leaves:recompute-end-dates')
            ->assertSuccessful();

        $this->assertSame('2026-08-28', $row->fresh()->end_date->toDateString());
        $this->assertNull($untouched->fresh()->end_date);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
            'entitled_days' => 20,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-28',
        ]);

        $this->artisan('annual-leaves:recompute-end-dates')
            ->expectsOutputToContain('шинэчилсэн: 0 мөр')
            ->assertSuccessful();
    }
}
