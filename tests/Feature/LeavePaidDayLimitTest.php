<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Жилийн цалинтай чөлөөний лимит — хэтэрсэн мөр «Цалингүй» болно.
 */
class LeavePaidDayLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function addLeave(User $admin, string $start, int $days): Leave
    {
        $this->actingAs($admin)
            ->post(route('leaves.store'), [
                'scope' => 'baiguullaga',
                'person_name' => 'А.Номин',
                'type' => 'tsalintai',
                'start_date' => $start,
                'days' => $days,
            ])
            ->assertRedirect();

        return Leave::query()->latest('id')->firstOrFail();
    }

    public function test_leave_over_the_yearly_cap_becomes_unpaid(): void
    {
        $admin = $this->admin();

        // Лимит 5 хоног — эхний 4 хоног цалинтай хэвээр.
        $first = $this->addLeave($admin, '2026-02-02', 4);
        $this->assertSame('tsalintai', $first->fresh()->type);
        $this->assertFalse($first->fresh()->limit_exceeded);

        // 4 + 3 = 7 > 5 тул хоёр дахь нь цалингүй.
        $second = $this->addLeave($admin, '2026-05-04', 3);
        $this->assertSame('tsalingui', $second->fresh()->type);
        $this->assertTrue($second->fresh()->limit_exceeded);

        // Эхнийх нь хөндөгдөөгүй.
        $this->assertSame('tsalintai', $first->fresh()->type);

        // Дараа жил дахин шинээр тооцогдоно.
        $next = $this->addLeave($admin, '2027-01-05', 5);
        $this->assertSame('tsalintai', $next->fresh()->type);
    }

    public function test_the_type_cannot_be_changed_back_to_paid(): void
    {
        $admin = $this->admin();

        $this->addLeave($admin, '2026-02-02', 5);
        $over = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertTrue($over->fresh()->limit_exceeded);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $over), ['type' => 'tsalintai'])
            ->assertRedirect();

        // Сервер лимитээ дахин тооцож, цалингүй болгож буцаана.
        $this->assertSame('tsalingui', $over->fresh()->type);
    }

    public function test_deleting_an_earlier_leave_frees_the_cap(): void
    {
        $admin = $this->admin();

        $first = $this->addLeave($admin, '2026-02-02', 5);
        $second = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertTrue($second->fresh()->limit_exceeded);

        $this->actingAs($admin)
            ->delete(route('leaves.destroy', $first))
            ->assertRedirect();

        $this->assertSame('tsalintai', $second->fresh()->type);
        $this->assertFalse($second->fresh()->limit_exceeded);
    }

    public function test_a_zero_cap_means_no_limit(): void
    {
        $admin = $this->admin();

        LeaveLimits::savePaidDaysPerYear(0);

        $leave = $this->addLeave($admin, '2026-02-02', 60);

        $this->assertSame('tsalintai', $leave->fresh()->type);
        $this->assertFalse($leave->fresh()->limit_exceeded);
    }

    public function test_changing_the_cap_recalculates_every_record(): void
    {
        $admin = $this->admin();

        $first = $this->addLeave($admin, '2026-02-02', 3);
        $second = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertSame('tsalintai', $second->fresh()->type);

        $this->actingAs($admin)
            ->patch(route('leaves.limits.update'), [
                'limits' => LeaveLimits::DEFAULTS,
                'paid_days_per_year' => 3,
            ])
            ->assertRedirect();

        $this->assertSame('tsalintai', $first->fresh()->type);
        $this->assertSame('tsalingui', $second->fresh()->type);
    }
}
