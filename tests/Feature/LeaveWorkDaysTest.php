<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Support\WorkDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Чөлөөний хугацаанд амралтын өдөр тооцогдохгүй.
 */
class LeaveWorkDaysTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_weekend_is_skipped_when_counting(): void
    {
        // 2026-11-12 — Пүрэв. 7 ажлын өдөр → 11-20 (Баасан).
        $this->assertSame(
            '2026-11-20',
            WorkDays::endDate('2026-11-12', 7)->toDateString(),
        );

        // Нэг хоног = эхлэх өдөр өөрөө.
        $this->assertSame(
            '2026-11-12',
            WorkDays::endDate('2026-11-12', 1)->toDateString(),
        );

        // Баасан гарагаас 2 хоног → дараагийн Даваа.
        $this->assertSame(
            '2026-11-16',
            WorkDays::endDate('2026-11-13', 2)->toDateString(),
        );
    }

    public function test_a_start_on_the_weekend_moves_to_the_next_work_day(): void
    {
        // 2026-11-14 — Бямба. Эхний ажлын өдөр нь 11-16 (Даваа).
        $this->assertSame(
            '2026-11-16',
            WorkDays::endDate('2026-11-14', 1)->toDateString(),
        );
    }

    public function test_a_new_leave_ends_on_a_work_day(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'baiguullaga',
            'person_name' => 'Ц.Энхтунгалаг',
            'type' => 'tsalintai',
            'start_date' => '2026-11-12',
            'days' => 7,
        ])->assertRedirect();

        $leave = Leave::query()->latest('id')->sole();

        $this->assertSame('2026-11-20', $leave->end_date->toDateString());
    }

    public function test_changing_the_day_count_recounts_work_days(): void
    {
        $admin = $this->admin();

        $leave = Leave::create([
            'user_id' => $admin->id,
            'person_name' => 'Ц.Энхтунгалаг',
            'type' => 'tsalintai',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'days' => 1,
        ]);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['days' => 3])
            ->assertRedirect();

        $this->assertSame('2026-11-16', $leave->fresh()->end_date->toDateString());
    }
}
