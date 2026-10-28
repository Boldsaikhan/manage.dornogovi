<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\IndicatorPeriod;
use App\Models\SectorIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Салбарын үзүүлэлт» — нэгж бүрийн суурь тоон мэдээлэл.
 */
class SectorIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $name): Department
    {
        return Department::create([
            'name' => $name,
            'code' => substr(md5($name), 0, 6),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function staff(Department $department, string $level = 'manage'): User
    {
        $user = User::factory()->create(['department_id' => $department->id]);
        $this->grantModule($user, 'legal_acts', $level);

        return $user->fresh();
    }

    private function period(string $label, bool $split = false): IndicatorPeriod
    {
        return IndicatorPeriod::create([
            'label' => $label,
            'year' => (int) $label,
            'split' => $split,
            'sort_order' => IndicatorPeriod::count() + 1,
        ]);
    }

    public function test_a_split_year_has_two_slots(): void
    {
        $this->assertSame(['year'], $this->period('2021')->slots());
        $this->assertSame(['half', 'year'], $this->period('2024', true)->slots());
    }

    public function test_the_page_lists_the_year_columns(): void
    {
        $unit = $this->department('Хэвлэл мэдээллийн алба');
        $user = $this->staff($unit);

        $this->period('2023');
        $this->period('2024', true);

        $this->actingAs($user)
            ->get(route('indicators.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/SectorIndicators')
                ->has('periods', 2)
                ->where('periods.0.label', '2023')
                ->where('periods.0.split', false)
                ->where('periods.1.split', true));
    }

    public function test_a_row_belongs_to_the_users_unit(): void
    {
        $unit = $this->department('Архивын тасаг');
        $user = $this->staff($unit);

        $this->actingAs($user)
            ->post(route('indicators.store'))
            ->assertRedirect();

        $row = SectorIndicator::firstOrFail();

        $this->assertSame($unit->id, $row->department_id);
        $this->assertSame('Архивын тасаг', $row->unit_name);
    }

    public function test_only_the_own_units_rows_are_shown(): void
    {
        $mine = $this->department('Архивын тасаг');
        $other = $this->department('Цэргийн штаб');

        $user = $this->staff($mine, 'edit');

        SectorIndicator::create(['department_id' => $mine->id, 'title' => 'Минийх']);
        SectorIndicator::create(['department_id' => $other->id, 'title' => 'Бусдынх']);

        $this->actingAs($user)
            ->get(route('indicators.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Минийх'));
    }

    public function test_a_cell_value_is_stored_and_cleared(): void
    {
        $unit = $this->department('Санхүү, төрийн сангийн хэлтэс');
        $user = $this->staff($unit);
        $period = $this->period('2024', true);

        $row = SectorIndicator::create(['department_id' => $unit->id, 'title' => 'ERP-д бүртгэсэн тоо']);

        $this->actingAs($user)
            ->patch(route('indicators.value', $row), [
                'period_id' => $period->id,
                'slot' => 'half',
                'value' => '148',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $row->id,
            'indicator_period_id' => $period->id,
            'slot' => 'half',
            'value' => '148',
        ]);

        // Хоосон утга илгээвэл мөр устана.
        $this->actingAs($user)
            ->patch(route('indicators.value', $row), [
                'period_id' => $period->id,
                'slot' => 'half',
                'value' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('sector_indicator_values', [
            'sector_indicator_id' => $row->id,
            'indicator_period_id' => $period->id,
            'slot' => 'half',
        ]);
    }

    public function test_another_units_row_cannot_be_edited(): void
    {
        $mine = $this->department('Архивын тасаг');
        $other = $this->department('Цэргийн штаб');

        $user = $this->staff($mine, 'edit');
        $row = SectorIndicator::create(['department_id' => $other->id, 'title' => 'Бусдынх']);

        $this->actingAs($user)
            ->patch(route('indicators.update', $row), ['title' => 'Өөрчилсөн'])
            ->assertForbidden();

        $this->assertSame('Бусдынх', $row->fresh()->title);
    }

    public function test_only_a_manager_can_add_a_year_column(): void
    {
        $unit = $this->department('Архивын тасаг');

        $editor = $this->staff($unit, 'edit');
        $this->actingAs($editor)
            ->post(route('indicators.periods.store'), ['label' => '2027 он'])
            ->assertForbidden();

        $manager = $this->staff($this->department('Хууль, эрх зүйн хэлтэс'), 'manage');
        $this->actingAs($manager)
            ->post(route('indicators.periods.store'), ['label' => '2027 он', 'split' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('indicator_periods', ['label' => '2027 он', 'split' => true]);
    }

    public function test_a_user_without_access_cannot_open_it(): void
    {
        $user = User::factory()->create(['department_id' => $this->department('Архивын тасаг')->id]);

        $this->actingAs($user)
            ->get(route('indicators.index'))
            ->assertForbidden();
    }
}
