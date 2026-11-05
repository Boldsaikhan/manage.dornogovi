<?php

namespace Tests\Feature;

use App\Models\ReportRowApproval;
use App\Models\User;
use App\Support\ReportFormula;
use App\Support\ReportRows;
use App\Support\ReportsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Гэрээний тайлан — хэрэгжилт, баталгаажуулалт, хяналтын үнэлгээ.
 */
class ReportContractWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT = 'contracts.ministry_gbxn';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $this->grantModule($user, 'reports', 'edit');

        return $user->fresh();
    }

    private function deptHead(): User
    {
        $user = User::factory()->create(['is_department_head' => true]);
        $this->grantModule($user, 'reports', 'edit');

        return $user->fresh();
    }

    /* ── Багана ──────────────────────────────────────────────── */

    public function test_a_contract_report_shows_the_new_columns(): void
    {
        $keys = collect(ReportsCatalog::find(self::REPORT)['columns'])->pluck('key')->all();

        foreach (['formula', 'target', 'actual', 'percent', 'report', 'dept_evaluation', 'monitor_evaluation'] as $key) {
            $this->assertContains($key, $keys, "Багана алга: {$key}");
        }
    }

    public function test_other_sections_keep_their_own_template(): void
    {
        $this->assertSame('contract_tracking', ReportsCatalog::find(self::REPORT)['template']);
        $this->assertSame('policy_tracking', ReportsCatalog::find('state_policy.cabinet_decree')['template']);
    }

    /* ── Томъёо ──────────────────────────────────────────────── */

    public function test_the_formula_divides_the_actual_by_the_target(): void
    {
        $this->assertSame(85.0, ReportFormula::percent(ReportFormula::GROWTH, 85, 100));
        $this->assertSame(50.0, ReportFormula::percent(ReportFormula::GROWTH, '1 000', '2,000'));

        // 100-аас дээшийг таслана.
        $this->assertSame(100.0, ReportFormula::percent(ReportFormula::GROWTH, 150, 100));

        // Тэг хуваарь, хоосон, томъёогүй — бодохгүй.
        $this->assertNull(ReportFormula::percent(ReportFormula::GROWTH, 5, 0));
        $this->assertNull(ReportFormula::percent(ReportFormula::GROWTH, '', 100));
        $this->assertNull(ReportFormula::percent('', 85, 100));
    }

    public function test_saving_the_actual_recalculates_the_percent(): void
    {
        $admin = $this->admin();

        foreach ([['formula', ReportFormula::GROWTH], ['target', '200'], ['actual', '150']] as [$column, $value]) {
            $this->actingAs($admin)
                ->patch(route('reports.rows.update', [self::REPORT, 0]), [
                    'column' => $column,
                    'value' => $value,
                ])
                ->assertRedirect();
        }

        $this->assertSame('75%', ReportRows::merged(self::REPORT)[0]['percent']);
    }

    public function test_without_a_formula_the_percent_is_left_alone(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('reports.rows.update', [self::REPORT, 0]), ['column' => 'percent', 'value' => '40%']);

        $this->actingAs($admin)
            ->patch(route('reports.rows.update', [self::REPORT, 0]), ['column' => 'actual', 'value' => '9']);

        $this->assertSame('40%', ReportRows::merged(self::REPORT)[0]['percent']);
    }

    /* ── Баталгаажуулалт ─────────────────────────────────────── */

    public function test_a_department_head_approves_a_row(): void
    {
        $this->actingAs($this->deptHead())
            ->patch(route('reports.rows.approve', [self::REPORT, 2]), ['approved' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('report_row_approvals', [
            'report_key' => self::REPORT,
            'row_index' => 2,
        ]);
    }

    public function test_an_ordinary_officer_cannot_approve(): void
    {
        $this->actingAs($this->staff())
            ->patch(route('reports.rows.approve', [self::REPORT, 2]), ['approved' => true])
            ->assertForbidden();

        $this->assertDatabaseCount('report_row_approvals', 0);
    }

    public function test_an_approval_can_be_taken_back(): void
    {
        $head = $this->deptHead();

        $this->actingAs($head)->patch(route('reports.rows.approve', [self::REPORT, 1]), ['approved' => true]);
        $this->actingAs($head)->patch(route('reports.rows.approve', [self::REPORT, 1]), ['approved' => false]);

        $this->assertDatabaseCount('report_row_approvals', 0);
    }

    /* ── Хяналтын үнэлгээ ────────────────────────────────────── */

    public function test_the_monitoring_mark_waits_for_the_approval(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('reports.rows.update', [self::REPORT, 3]), [
                'column' => 'monitor_evaluation',
                'value' => 'Хангалттай',
            ])
            ->assertStatus(422);

        ReportRowApproval::create([
            'report_key' => self::REPORT,
            'row_index' => 3,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('reports.rows.update', [self::REPORT, 3]), [
                'column' => 'monitor_evaluation',
                'value' => 'Хангалттай',
            ])
            ->assertRedirect();

        $this->assertSame('Хангалттай', ReportRows::merged(self::REPORT)[3]['monitor_evaluation']);
    }

    public function test_only_monitoring_staff_fill_the_monitoring_mark(): void
    {
        ReportRowApproval::create([
            'report_key' => self::REPORT,
            'row_index' => 4,
            'approved_at' => now(),
        ]);

        $this->actingAs($this->deptHead())
            ->patch(route('reports.rows.update', [self::REPORT, 4]), [
                'column' => 'monitor_evaluation',
                'value' => 'Оролдлого',
            ])
            ->assertForbidden();
    }

    public function test_a_department_can_still_fill_its_own_evaluation(): void
    {
        $this->actingAs($this->staff())
            ->patch(route('reports.rows.update', [self::REPORT, 5]), [
                'column' => 'dept_evaluation',
                'value' => 'Бүрэн хэрэгжсэн',
            ])
            ->assertRedirect();

        $this->assertSame('Бүрэн хэрэгжсэн', ReportRows::merged(self::REPORT)[5]['dept_evaluation']);
    }

    /* ── Гэрээ ирүүлэх хугацаа ───────────────────────────────── */

    public function test_the_deadline_is_stored_and_shown(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('reports.schedule', self::REPORT), [
                'due_on' => '2026-12-01',
                'note' => 'Хагас жилийн тайлантай хамт',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('reports.show', self::REPORT))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('workflow.due.due_on', '2026-12-01')
                ->where('workflow.due.note', 'Хагас жилийн тайлантай хамт')
                ->where('workflow.schedule', true)
                ->where('workflow.approval', true));
    }

    public function test_an_ordinary_officer_cannot_set_the_deadline(): void
    {
        $this->actingAs($this->staff())
            ->patch(route('reports.schedule', self::REPORT), ['due_on' => '2026-12-01'])
            ->assertForbidden();
    }
}
