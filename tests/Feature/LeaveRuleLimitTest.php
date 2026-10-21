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

    public function test_each_scope_keeps_its_own_limits(): void
    {
        $admin = $this->admin();

        // АЗДТГ-д 3 хоног, агентлагт 10 хоног.
        LeaveLimits::save([
            ['code' => 'Y', 'label' => 'Жилийн заалт', 'period' => 'year', 'days' => 3, 'pay' => 'tsalintai'],
        ], 'baiguullaga');

        LeaveLimits::save([
            ['code' => 'Y', 'label' => 'Жилийн заалт', 'period' => 'year', 'days' => 10, 'pay' => 'tsalintai'],
        ], 'agentlag');

        $this->assertSame(3, LeaveLimits::all('baiguullaga')[0]['days']);
        $this->assertSame(10, LeaveLimits::all('agentlag')[0]['days']);

        // АЗДТГ-ийн мөр 4 хоног авбал лимит хэтэрч цалингүй болно.
        $office = $this->leave('2026-02-02', 4);
        $office->update(['scope' => 'baiguullaga']);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $office), ['rule_code' => 'Y'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $office->fresh()->type);

        // Агентлагийн мөрд 4 хоног лимитэд багтана.
        $agency = $this->leave('2026-02-02', 4);
        $agency->update(['scope' => 'agentlag']);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $agency), ['rule_code' => 'Y'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalintai', $agency->fresh()->type);
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

    public function test_the_rule_sets_the_pay_type(): void
    {
        LeaveLimits::save([
            ['code' => 'P', 'label' => 'Цалинтай заалт', 'period' => 'year', 'days' => 10, 'pay' => 'tsalintai'],
            ['code' => 'U', 'label' => 'Цалингүй заалт', 'period' => 'year', 'days' => 10, 'pay' => 'tsalingui'],
            ['code' => 'N', 'label' => 'Заагаагүй заалт', 'period' => 'year', 'days' => 10, 'pay' => 'none'],
        ]);

        $admin = $this->admin();
        $leave = $this->leave('2026-02-02', 1);

        // Тохиргоонд «Цалингүй» гэснийг сонгоход төрөл нь дагана.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['rule_code' => 'U'])
            ->assertRedirect();

        $this->assertSame('tsalingui', $leave->fresh()->type);

        // «Цалинтай» заалт сонгоход буцаад цалинтай болно.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['rule_code' => 'P'])
            ->assertRedirect();

        $this->assertSame('tsalintai', $leave->fresh()->type);

        // «—» заалт төрлийг хөндөхгүй.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['type' => 'eeljiin'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['rule_code' => 'N'])
            ->assertRedirect();

        $this->assertSame('eeljiin', $leave->fresh()->type);
    }

    public function test_the_type_is_locked_when_the_rule_decides_it(): void
    {
        LeaveLimits::save([
            ['code' => 'P', 'label' => 'Цалинтай заалт', 'period' => 'year', 'days' => 10, 'pay' => 'tsalintai'],
            ['code' => 'N', 'label' => 'Заагаагүй заалт', 'period' => 'year', 'days' => 10, 'pay' => 'none'],
        ]);

        $admin = $this->admin();
        $locked = $this->leave('2026-02-02', 1, 'P');
        $open = $this->leave('2026-03-02', 1, 'N');

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($locked, $open): void {
                $rows = collect($page->toArray()['props']['rows'])->keyBy('id');

                $this->assertTrue($rows[$locked->id]['type_locked']);
                $this->assertFalse($rows[$open->id]['type_locked']);
            });
    }

    public function test_a_rule_over_its_yearly_limit_becomes_unpaid(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 4, 'Y');
        $second = $this->leave('2026-06-02', 3);

        // 4 + 3 = 7 > 5 — сонгоход саад болохгүй, гэхдээ цалингүй болно.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $second), ['rule_code' => 'Y'])
            ->assertSessionHasNoErrors();

        $fresh = $second->fresh();
        $this->assertSame('Y', $fresh->rule_code);
        $this->assertSame('tsalingui', $fresh->type);
    }

    public function test_the_quarterly_limit_resets_next_quarter(): void
    {
        $admin = $this->admin();

        $this->leave('2026-01-10', 3, 'Q');

        // Хоёрдугаар улирал — лимит дахин шинэ тул цалинтай хэвээр.
        $next = $this->leave('2026-04-10', 3);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $next), ['rule_code' => 'Q'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalintai', $next->fresh()->type);

        // Мөн улиралд дахин авбал лимит дүүрсэн тул цалингүй.
        $same = $this->leave('2026-02-10', 1);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $same), ['rule_code' => 'Q'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $same->fresh()->type);
    }

    public function test_a_per_occasion_rule_only_caps_this_leave(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 2, 'O');
        $short = $this->leave('2026-03-02', 2);
        $long = $this->leave('2026-04-02', 3);

        // Өмнөх тохиолдол саад болохгүй — цалинтай хэвээр.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $short), ['rule_code' => 'O'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalintai', $short->fresh()->type);

        // Нэг удаад 2 хоногоос хэтэрвэл цалингүй.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $long), ['rule_code' => 'O'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $long->fresh()->type);
    }

    public function test_raising_the_days_past_the_limit_turns_it_unpaid(): void
    {
        $admin = $this->admin();

        $leave = $this->leave('2026-02-02', 2, 'Y');

        // 5 хоногийн лимит — 5 хүртэл цалинтай хэвээр.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['days' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $leave->fresh()->days);
        $this->assertSame('tsalintai', $leave->fresh()->type);

        // 6 бол хэтэрнэ — хадгалагдана, гэхдээ цалингүй болно.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['days' => 6])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $leave->fresh()->days);
        $this->assertSame('tsalingui', $leave->fresh()->type);
    }

    public function test_the_days_of_other_rows_count_toward_the_limit(): void
    {
        $admin = $this->admin();

        $this->leave('2026-02-02', 3, 'Y');
        $second = $this->leave('2026-06-02', 1, 'Y');

        // 3 + 2 = 5 — яг багтана.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $second), ['days' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalintai', $second->fresh()->type);

        // 3 + 3 = 6 — хэтэрнэ.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $second), ['days' => 3])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $second->fresh()->type);
    }

    public function test_a_per_occasion_rule_caps_the_days_too(): void
    {
        $admin = $this->admin();

        $leave = $this->leave('2026-02-02', 1, 'O');

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['days' => 3])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $leave->fresh()->days);
        $this->assertSame('tsalingui', $leave->fresh()->type);
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

    public function test_the_balance_runs_down_row_by_row(): void
    {
        $admin = $this->admin();

        // Жилийн заалт: 5 хоног. 2 → 1 → 2 гэж дараалан авна.
        $first = $this->leave('2026-02-02', 2, 'Y');
        $second = $this->leave('2026-03-02', 1, 'Y');
        $third = $this->leave('2026-04-02', 2, 'Y');

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($first, $second, $third): void {
                $rows = collect($page->toArray()['props']['rows'])->keyBy('id');

                // Мөр бүр дээр өмнөхүүд нь хуримтлагдана.
                $this->assertSame(2, $rows[$first->id]['rule_usage']['Y']);
                $this->assertSame(3, $rows[$second->id]['rule_usage']['Y']);
                $this->assertSame(5, $rows[$third->id]['rule_usage']['Y']);
            });

        // Лимит дүүрсэн тул шинэ мөр цалингүй болно.
        $fresh = $this->leave('2026-05-02', 1);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $fresh), ['rule_code' => 'Y'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $fresh->fresh()->type);
        $this->assertTrue((bool) $this->rowProp($admin, $fresh->id, 'paid_blocked'));
    }

    /** Хүснэгтийн мөрний нэг талбарыг уншина. */
    private function rowProp(User $admin, int $id, string $key): mixed
    {
        $value = null;

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertInertia(function (AssertableInertia $page) use ($id, $key, &$value): void {
                $value = collect($page->toArray()['props']['rows'])->firstWhere('id', $id)[$key] ?? null;
            });

        return $value;
    }

    public function test_a_later_leave_counts_even_for_an_earlier_row(): void
    {
        $admin = $this->admin();

        // Улирлын заалт: 3 хоног. Сүүлд бүртгэсэн ч огноо нь хожуу.
        $this->leave('2026-02-20', 3, 'Q');

        // Шинэ мөр нь огноогоор өмнө — гэхдээ улирлын лимит дүүрсэн.
        $earlier = $this->leave('2026-02-10', 1);

        $this->assertSame(3, $this->ruleUsage($admin, $earlier->id, 'Q'));

        $this->actingAs($admin)
            ->patch(route('leaves.update', $earlier), ['rule_code' => 'Q'])
            ->assertSessionHasNoErrors();

        $this->assertSame('tsalingui', $earlier->fresh()->type);
    }

    /** Мөрөнд харагдах заалтын зарцуулалт. */
    private function ruleUsage(User $admin, int $id, string $code): int
    {
        return (int) ($this->rowProp($admin, $id, 'rule_usage')[$code] ?? 0);
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
