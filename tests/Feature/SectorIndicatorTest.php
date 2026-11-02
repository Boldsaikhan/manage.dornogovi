<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\IndicatorPeriod;
use App\Models\SectorIndicator;
use App\Models\SectorIndicatorCategory;
use App\Models\SectorIndicatorValue;
use App\Models\User;
use App\Support\IndicatorMeasure;
use App\Support\SectorIndicatorCatalog;
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
        $this->grantModule($user, 'sector_indicators', $level);

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

    public function test_visibility_follows_the_configured_scope(): void
    {
        $mine = $this->department('Архивын тасаг');
        $other = $this->department('Цэргийн штаб');

        SectorIndicator::create(['department_id' => $mine->id, 'title' => 'Минийх']);
        SectorIndicator::create(['department_id' => $other->id, 'title' => 'Бусдынх']);

        $unitLead = $this->staff($mine, 'manage_unit');

        $this->actingAs($unitLead)
            ->get(route('indicators.index', ['unit' => $other->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Минийх')
                ->where('canPickUnit', false)
                ->where('units', []));

        $editor = $this->staff($mine, 'edit');

        $this->actingAs($editor)
            ->get(route('indicators.index', ['unit' => $other->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Бусдынх')
                ->where('unitId', $other->id)
                ->where('canPickUnit', true)
                ->where('units', fn ($units) => collect($units)->contains('id', $other->id)));
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

        $lead = $this->staff($mine, 'manage_unit');
        $row = SectorIndicator::create(['department_id' => $other->id, 'title' => 'Бусдынх']);

        $this->actingAs($lead)
            ->patch(route('indicators.update', $row), ['title' => 'Өөрчилсөн'])
            ->assertForbidden();

        $this->assertSame('Бусдынх', $row->fresh()->title);

        $editor = $this->staff($mine, 'edit');

        $this->actingAs($editor)
            ->patch(route('indicators.update', $row), ['title' => 'Өөрчилсөн'])
            ->assertRedirect();

        $this->assertSame('Өөрчилсөн', $row->fresh()->title);
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

        $period = IndicatorPeriod::query()->where('label', '2027 он')->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('indicators.periods.update', $period), ['label' => '2028', 'year' => 2028])
            ->assertRedirect();

        $this->assertSame('2028', $period->fresh()->label);
        $this->assertSame(2028, $period->fresh()->year);
    }

    public function test_measure_unit_is_chosen_and_shapes_the_numbers(): void
    {
        $unit = $this->department('Хэвлэл мэдээллийн алба');
        $user = $this->staff($unit, 'edit');
        $period = $this->period('2025');
        $row = SectorIndicator::create(['department_id' => $unit->id, 'title' => 'ERP систем']);

        $this->actingAs($user)
            ->patch(route('indicators.update', $row), ['measure_unit' => 'дурын'])
            ->assertSessionHasErrors('measure_unit');

        $this->actingAs($user)
            ->patch(route('indicators.update', $row), ['measure_unit' => 'хувь'])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('indicators.update', $row), ['target' => '45,5%'])
            ->assertRedirect();

        $this->assertSame('хувь', $row->fresh()->measure_unit);
        $this->assertSame('45.5', $row->fresh()->target);

        $this->actingAs($user)
            ->patch(route('indicators.update', $row), ['measure_unit' => 'тоо'])
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('indicators.value', $row), [
                'period_id' => $period->id,
                'slot' => 'year',
                'value' => '1 200 ширхэг',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $row->id,
            'value' => '1200',
        ]);

        $this->assertSame('1200', IndicatorMeasure::normalize('тоо', '1 200'));
        $this->assertSame('80', IndicatorMeasure::normalize('төгрөг', '80.4₮'));
    }

    public function test_the_employee_dashboard_charts_the_units_baseline(): void
    {
        $mine = $this->department('Архивын тасаг');
        $other = $this->department('Цэргийн штаб');
        $user = $this->staff($mine, 'edit');
        $period = $this->period('2024');

        $row = SectorIndicator::create([
            'department_id' => $mine->id,
            'title' => 'ERP систем',
            'measure_unit' => 'тоо',
            'target' => '200',
            'sort_order' => 1,
        ]);
        SectorIndicatorValue::create([
            'sector_indicator_id' => $row->id,
            'indicator_period_id' => $period->id,
            'slot' => 'year',
            'value' => '150',
        ]);
        SectorIndicator::create([
            'department_id' => $other->id,
            'title' => 'Нууц',
            'measure_unit' => 'хувь',
            'target' => '100',
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('indicatorDashboard.unitId', $mine->id)
                ->has('indicatorDashboard.rows', 1)
                ->where('indicatorDashboard.rows.0.title', 'ERP систем')
                ->where('indicatorDashboard.rows.0.points.0.value', 150)
                ->where('indicatorDashboard.rows.0.progress', 75)
                ->where('indicatorDashboard.units', fn ($units) => collect($units)->contains('id', $other->id)));

        $this->actingAs($user)
            ->get(route('dept.dashboard', ['unit' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('indicatorDashboard.unitId', $other->id)
                ->where('indicatorDashboard.rows.0.title', 'Нууц'));

        $lead = $this->staff($mine, 'manage_unit');

        $this->actingAs($lead)
            ->get(route('dept.dashboard', ['unit' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('indicatorDashboard.unitId', $mine->id)
                ->where('indicatorDashboard.rows.0.title', 'ERP систем')
                ->where('indicatorDashboard.units', []));

        $admin = User::factory()->create([
            'is_admin' => true,
            'department_id' => $mine->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dept.dashboard', ['unit' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('indicatorDashboard.unitId', $other->id)
                ->where('indicatorDashboard.rows.0.title', 'Нууц')
                ->has('indicatorDashboard.units'));
    }

    public function test_the_administration_catalog_fills_the_unit_once(): void
    {
        $unit = $this->department(SectorIndicatorCatalog::UNIT);
        $other = $this->department('Санхүү, төсвийн хэлтэс');
        $short = SectorIndicator::create([
            'department_id' => $unit->id,
            'unit_name' => $unit->name,
            'title' => 'ERP системд бүртгэсэн',
            'sort_order' => 50,
        ]);

        SectorIndicatorCatalog::import();
        SectorIndicatorCatalog::import();

        $this->assertSame(25, SectorIndicator::query()->where('department_id', $unit->id)->count());
        $this->assertSame(0, SectorIndicator::query()->where('department_id', $other->id)->count());

        $short->refresh();
        $this->assertSame('ERP системд бүртгэсэн байгууллагын тоо', $short->title);
        $this->assertSame('тоо', $short->measure_unit);
        $this->assertSame('180', $short->target);
        $this->assertSame(1, $short->sort_order);

        $year2021 = IndicatorPeriod::query()->where('year', 2021)->first();
        $year2024 = IndicatorPeriod::query()->where('year', 2024)->first();
        $year2025 = IndicatorPeriod::query()->where('year', 2025)->first();
        $this->assertNotNull($year2021);
        $this->assertFalse($year2021->split);
        $this->assertTrue($year2024->split);

        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $short->id,
            'indicator_period_id' => $year2021->id,
            'slot' => 'year',
            'value' => '55',
        ]);

        $percent = SectorIndicator::query()->where('title', 'shilen.gov.mn сайтын үнэлгээний хувь')->first();
        $this->assertSame('хувь', $percent->measure_unit);
        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $percent->id,
            'indicator_period_id' => $year2024->id,
            'slot' => 'year',
            'value' => '80',
        ]);
        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $percent->id,
            'indicator_period_id' => $year2025->id,
            'slot' => 'half',
            'value' => '100',
        ]);

        $contract = SectorIndicator::query()->where('title', 'ЦХНХХС-н гэрээний хэрэгжилт')->first();
        $this->assertSame('100', $contract->target);
        $this->assertDatabaseHas('sector_indicator_values', [
            'sector_indicator_id' => $contract->id,
            'indicator_period_id' => IndicatorPeriod::query()->where('year', 2022)->value('id'),
            'slot' => 'year',
            'value' => '92.7',
        ]);
    }

    public function test_a_category_groups_rows_and_stays_inside_the_unit(): void
    {
        $mine = $this->department('Архивын тасаг');
        $other = $this->department('Цэргийн штаб');
        $editor = $this->staff($mine, 'edit');
        $lead = $this->staff($other, 'manage_unit');

        $this->actingAs($editor)
            ->post(route('indicators.categories.store'), ['name' => 'Цахимжилт'])
            ->assertRedirect();

        $category = SectorIndicatorCategory::query()->firstOrFail();
        $this->assertSame($mine->id, $category->department_id);

        $this->actingAs($editor)
            ->post(route('indicators.store'), ['category_id' => $category->id])
            ->assertRedirect();

        $row = SectorIndicator::query()->firstOrFail();
        $this->assertSame($category->id, $row->category_id);

        $this->actingAs($editor)
            ->get(route('indicators.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Цахимжилт')
                ->where('rows.0.category_id', $category->id));

        $this->actingAs($lead)
            ->get(route('indicators.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('categories', 0)
                ->has('rows', 0));

        $this->actingAs($lead)
            ->delete(route('indicators.categories.destroy', $category))
            ->assertForbidden();

        $this->actingAs($editor)
            ->delete(route('indicators.categories.destroy', $category))
            ->assertRedirect();

        $this->assertNull($row->fresh()->category_id);
    }

    public function test_a_user_without_access_cannot_open_it(): void
    {
        $user = User::factory()->create(['department_id' => $this->department('Архивын тасаг')->id]);

        $this->actingAs($user)
            ->get(route('indicators.index'))
            ->assertForbidden();
    }
}
