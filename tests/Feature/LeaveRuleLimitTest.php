<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Журам» багана — заалт тус бүрийн лимит.
 */
class LeaveRuleLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();

        // Шалгахад ойлгомжтой байхаар хоёр заалт үлдээнэ.
        LeaveLimits::save([
            ['code' => 'Y', 'label' => 'Жилийн заалт', 'period' => 'year', 'days' => 5, 'pay' => 'tsalintai'],
            ['code' => 'Q', 'label' => 'Улирлын заалт', 'period' => 'quarter', 'days' => 3, 'pay' => 'tsalintai'],
            ['code' => 'O', 'label' => 'Тохиолдлын заалт', 'period' => 'once', 'days' => 2, 'pay' => 'tsalintai'],
        ]);

        // Цалинтай хоногийн тусдаа лимит энэ тестэд саад болохгүй.
        LeaveLimits::savePaidDaysPerYear(0);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function leave(string $start, int $days, ?string $rule = null): Leave
    {
        return Leave::query()->create([
            'user_id' => $this->admin()->id,
            'scope' => 'baiguullaga',
            'person_name' => 'А.Номин',
            'type' => 'tsalintai',
            'rule_code' => $rule,
            'start_date' => $start,
            'end_date' => $start,
            'days' => $days,
            'status' => 'approved',
        ]);
    }

    public function test_a_rule_can_be_chosen_while_it_fits(): void
    {
        $admin = $this->admin();
        $leave = $this->leave('2026-02-02', 3);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['rule_code' => 'Y'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Y', $leave->fresh()->rule_code);
    }

    public function test_a_rule_over_its_yearly_limit_is_refused(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 4, 'Y');
        $second = $this->leave('2026-06-02', 3);

        // 4 + 3 = 7 > 5
        $this->actingAs($admin)
            ->patch(route('leaves.update', $second), ['rule_code' => 'Y'])
            ->assertSessionHasErrors('rule_code');

        $this->assertNull($second->fresh()->rule_code);
    }

    public function test_the_quarterly_limit_resets_next_quarter(): void
    {
        $admin = $this->admin();

        $this->leave('2026-01-10', 3, 'Q');

        // Хоёрдугаар улирал — лимит дахин шинэ.
        $next = $this->leave('2026-04-10', 3);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $next), ['rule_code' => 'Q'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Q', $next->fresh()->rule_code);

        // Мөн улиралд дахин авбал хаагдана.
        $same = $this->leave('2026-02-10', 1);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $same), ['rule_code' => 'Q'])
            ->assertSessionHasErrors('rule_code');
    }

    public function test_a_per_occasion_rule_only_caps_this_leave(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 2, 'O');
        $short = $this->leave('2026-03-02', 2);
        $long = $this->leave('2026-04-02', 3);

        // Өмнөх тохиолдол саад болохгүй.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $short), ['rule_code' => 'O'])
            ->assertSessionHasNoErrors();

        // Гэхдээ нэг удаад 2 хоногоос хэтэрч болохгүй.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $long), ['rule_code' => 'O'])
            ->assertSessionHasErrors('rule_code');
    }

    public function test_the_register_reports_how_many_days_are_used(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 3, 'Y');
        $this->leave('2026-01-10', 2, 'Q');
        $this->leave('2026-06-02', 1);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $fresh = collect($page->toArray()['props']['rows'])
                    ->firstWhere('rule_code', null);

                // Жилийн заалтаар 3 хоног авсан.
                $this->assertSame(3, $fresh['rule_usage']['Y']);

                // Улирал өөр тул улирлын заалтын хуримтлал 0.
                $this->assertSame(0, $fresh['rule_usage']['Q']);

                // «Тохиолдол бүрт» заалтад хуримтлал байхгүй.
                $this->assertSame(0, $fresh['rule_usage']['O']);
            });
    }

    public function test_the_register_marks_the_blocked_rules(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 5, 'Y');
        $this->leave('2026-06-02', 3);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $rows = collect($page->toArray()['props']['rows']);

                // Шинэ мөрөнд жилийн заалт дүүрсэн тул хаалттай.
                $fresh = $rows->firstWhere('rule_code', null);
                $this->assertContains('Y', $fresh['blocked_rules']);

                // Аль хэдийн тэр заалтаар олгосон мөрөнд нь хаалттай биш.
                $used = $rows->firstWhere('rule_code', 'Y');
                $this->assertNotContains('Y', $used['blocked_rules']);
            });
    }
}
