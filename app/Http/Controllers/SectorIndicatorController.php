<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\IndicatorPeriod;
use App\Models\SectorIndicator;
use App\Models\SectorIndicatorCategory;
use App\Models\SectorIndicatorValue;
use App\Support\IndicatorMeasure;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Салбарын үзүүлэлт — нэгж бүрийн суурь тоон мэдээлэл.
 *
 * Жилийн багануудыг гараар нэмж, нэг жилийг «хагас жил / жилийн эцэс»
 * гэж хоёр нүдтэй болгож болно. «Бүгд» эрхтэй хүн нэгж сонгоно,
 * «хэлтсийн» эрхтэй хүн зөвхөн өөрийн нэгжээ харна.
 */
class SectorIndicatorController extends Controller
{
    private const MODULE = 'sector_indicators';

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canView($user, self::MODULE), 403);

        $isManager = $user->is_admin || ModuleAccess::canManage($user, self::MODULE);
        $canPickUnit = ModuleAccess::unrestrictedScope($user, self::MODULE);

        $units = Department::query()
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
            ->values();

        $unitId = $this->resolveUnit($request, $user, $canPickUnit, $units->pluck('id')->all());

        $periods = IndicatorPeriod::ordered()->get()
            ->map(fn (IndicatorPeriod $p) => [
                'id' => $p->id,
                'label' => $p->label,
                'split' => $p->split,
                'slots' => $p->slots(),
            ])
            ->values();

        $rows = SectorIndicator::query()
            ->with('values:id,sector_indicator_id,indicator_period_id,slot,value')
            ->when($unitId, fn ($q) => $q->where('department_id', $unitId))
            ->when(! $unitId, fn ($q) => $q->whereNull('department_id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (SectorIndicator $row, int $i) {
                $values = [];

                foreach ($row->values as $value) {
                    $values[$value->indicator_period_id.':'.$value->slot] = $value->value;
                }

                return [
                    'id' => $row->id,
                    'no' => $i + 1,
                    'category_id' => $row->category_id,
                    'title' => $row->title,
                    'measure_unit' => $row->measure_unit,
                    'target' => $row->target,
                    'note' => $row->note,
                    'values' => $values,
                ];
            })
            ->values();

        $categories = SectorIndicatorCategory::query()
            ->when($unitId, fn ($q) => $q->where('department_id', $unitId))
            ->when(! $unitId, fn ($q) => $q->whereNull('department_id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (SectorIndicatorCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values();

        return Inertia::render('Modules/SectorIndicators', [
            'units' => $canPickUnit ? $units : [],
            'unitId' => $unitId,
            'categories' => $categories,
            'periods' => $periods,
            'rows' => $rows,
            'canEdit' => ModuleAccess::canEdit($user, self::MODULE),
            'canManage' => $isManager,
            'canPickUnit' => $canPickUnit,
            'measureUnits' => IndicatorMeasure::options(),
        ]);
    }

    /** Аль нэгжийн хүснэгтийг харуулах вэ. */
    private function resolveUnit(Request $request, $user, bool $canPickUnit, array $allowed): ?int
    {
        if (! $canPickUnit) {
            $own = $user->department_id ? (int) $user->department_id : null;

            return $own && in_array($own, $allowed, true) ? $own : null;
        }

        $requested = (int) $request->integer('unit');

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        if ($user->department_id && in_array((int) $user->department_id, $allowed, true)) {
            return (int) $user->department_id;
        }

        return $allowed[0] ?? null;
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);

        $unitId = $this->writableUnit($request, $user);

        $next = (int) SectorIndicator::query()
            ->when($unitId, fn ($q) => $q->where('department_id', $unitId))
            ->max('sort_order');

        SectorIndicator::create([
            'department_id' => $unitId,
            'category_id' => $this->categoryForUnit($request, $unitId),
            'unit_name' => $unitId ? Department::find($unitId)?->name : null,
            'sort_order' => $next + 1,
            'created_by' => $user->id,
        ]);

        return back(303)->with('success', 'Мөр нэмлээ.');
    }

    public function update(Request $request, SectorIndicator $indicator): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);
        $this->authorizeRow($user, $indicator);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'measure_unit' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(IndicatorMeasure::units()))],
            'target' => ['sometimes', 'nullable', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (array_key_exists('category_id', $data)) {
            $data['category_id'] = $this->ownedCategoryId($data['category_id'], $indicator->department_id);
        }

        $unit = array_key_exists('measure_unit', $data)
            ? $data['measure_unit']
            : $indicator->measure_unit;

        if (array_key_exists('target', $data)) {
            $data['target'] = IndicatorMeasure::normalize($unit, $data['target']) ?: null;
        }

        $indicator->fill($data)->save();

        return back(303);
    }

    /** Нэг нүдний утгыг хадгална. */
    public function updateValue(Request $request, SectorIndicator $indicator): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);
        $this->authorizeRow($user, $indicator);

        $data = $request->validate([
            'period_id' => ['required', Rule::exists('indicator_periods', 'id')],
            'slot' => ['required', Rule::in(array_keys(IndicatorPeriod::SLOTS))],
            'value' => ['nullable', 'string', 'max:60'],
        ]);

        $value = IndicatorMeasure::normalize($indicator->measure_unit, $data['value'] ?? '');

        $query = SectorIndicatorValue::query()
            ->where('sector_indicator_id', $indicator->id)
            ->where('indicator_period_id', $data['period_id'])
            ->where('slot', $data['slot']);

        if ($value === '') {
            $query->delete();

            return back(303);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update(['value' => $value]);
        } else {
            SectorIndicatorValue::create([
                'sector_indicator_id' => $indicator->id,
                'indicator_period_id' => $data['period_id'],
                'slot' => $data['slot'],
                'value' => $value,
            ]);
        }

        return back(303);
    }

    public function destroy(Request $request, SectorIndicator $indicator): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);
        $this->authorizeRow($user, $indicator);

        $indicator->delete();

        return back(303)->with('success', 'Мөрийг устгалаа.');
    }

    /* ── Ангилал ───────────────────────────────────────────────────── */

    public function storeCategory(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);

        $unitId = $this->writableUnit($request, $user);
        abort_unless($unitId, 422);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        abort_if($name === '', 422);

        $next = (int) SectorIndicatorCategory::query()
            ->where('department_id', $unitId)
            ->max('sort_order');

        SectorIndicatorCategory::create([
            'department_id' => $unitId,
            'name' => $name,
            'sort_order' => $next + 1,
        ]);

        return back(303)->with('success', 'Ангилал нэмлээ.');
    }

    public function updateCategory(Request $request, SectorIndicatorCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);
        $this->authorizeCategory($user, $category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $category->update(['name' => trim($data['name'])]);

        return back(303);
    }

    public function destroyCategory(Request $request, SectorIndicatorCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_unless(ModuleAccess::canEdit($user, self::MODULE), 403);
        $this->authorizeCategory($user, $category);

        $category->delete();

        return back(303)->with('success', 'Ангиллыг устгалаа.');
    }

    /* ── Жилийн багана ─────────────────────────────────────────────── */

    public function storePeriod(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'split' => ['nullable', 'boolean'],
        ]);

        IndicatorPeriod::create([
            'label' => $data['label'],
            'year' => $data['year'] ?? null,
            'split' => (bool) ($data['split'] ?? false),
            'sort_order' => ((int) IndicatorPeriod::max('sort_order')) + 1,
        ]);

        return back(303)->with('success', 'Баганыг нэмлээ.');
    }

    public function updatePeriod(Request $request, IndicatorPeriod $period): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:40'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2200'],
            'split' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $period->fill($data)->save();

        return back(303);
    }

    public function destroyPeriod(Request $request, IndicatorPeriod $period): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        // Тухайн баганын утгууд хамт устана.
        $period->delete();

        return back(303)->with('success', 'Баганыг устгалаа.');
    }

    private function writableUnit(Request $request, $user): ?int
    {
        if (ModuleAccess::unrestrictedScope($user, self::MODULE)) {
            $requested = (int) $request->integer('unit');

            if ($requested && Department::whereKey($requested)->exists()) {
                return $requested;
            }
        }

        return $user->department_id ? (int) $user->department_id : null;
    }

    /** Өөр нэгжийн мөрийг хөндөхгүй. «Бүгд» эрх нэгжээр хязгаарлагдахгүй. */
    private function authorizeRow($user, SectorIndicator $indicator): void
    {
        if (ModuleAccess::unrestrictedScope($user, self::MODULE)) {
            return;
        }

        abort_unless(
            (int) $indicator->department_id === (int) $user->department_id,
            403,
        );
    }

    private function authorizeCategory($user, SectorIndicatorCategory $category): void
    {
        if (ModuleAccess::unrestrictedScope($user, self::MODULE)) {
            return;
        }

        abort_unless(
            (int) $category->department_id === (int) $user->department_id,
            403,
        );
    }

    private function categoryForUnit(Request $request, ?int $unitId): ?int
    {
        return $this->ownedCategoryId($request->input('category_id'), $unitId);
    }

    private function ownedCategoryId(mixed $categoryId, ?int $unitId): ?int
    {
        $id = (int) $categoryId;

        if (! $id || ! $unitId) {
            return null;
        }

        $belongs = SectorIndicatorCategory::query()
            ->whereKey($id)
            ->where('department_id', $unitId)
            ->exists();

        abort_unless($belongs, 422);

        return $id;
    }
}
