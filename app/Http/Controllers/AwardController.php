<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Support\ModuleAccess;
use App\Support\XlsxTableWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AwardController extends Controller
{
    private const MODULE = 'awards';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $tab = $this->normalizeTab((string) $request->query('tab', 'state_high'));
        $year = $this->normalizeYear($request->query('year'));
        $subtype = $this->normalizeSubtypeFilter($tab, (string) $request->query('subtype', ''));

        $counts = Award::query()
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $query = Award::query()->orderByDesc('year')->orderBy('id');
        $query->where('category', $tab);

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($subtype !== '') {
            $query->where('subtype', $subtype);
        }

        $rows = $query
            ->limit(500)
            ->get()
            ->values()
            ->map(fn (Award $award, int $i) => $this->serialize($award, $i + 1));

        $years = Award::query()
            ->where('category', $tab)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        return Inertia::render('Modules/Awards', [
            'tab' => $tab,
            'tabs' => collect(Award::CATEGORIES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
                'count' => (int) ($counts[$value] ?? 0),
            ])->values()->all(),
            'subtype' => $subtype,
            'subtypes' => $this->subtypeOptions($tab),
            'year' => $year,
            'years' => $years,
            'columns' => $this->columnsFor($tab),
            'rows' => $rows,
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
            'categories' => Award::CATEGORIES,
            'allSubtypes' => Award::SUBTYPES,
            'categorySubtypes' => Award::CATEGORY_SUBTYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Award::query()->create($data);

        return redirect()
            ->route('awards.index', array_filter([
                'tab' => $data['category'],
                'subtype' => $data['subtype'] ?? null,
                'year' => $data['year'] ?? null,
            ]))
            ->with('success', 'Шагналын бүртгэл хадгалагдлаа.');
    }

    public function update(Request $request, Award $award): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $this->validated($request, $award);
        $award->update($data);

        return redirect()
            ->route('awards.index', array_filter([
                'tab' => $award->category,
                'subtype' => $award->subtype,
                'year' => $award->year,
            ]))
            ->with('success', 'Шинэчиллээ.');
    }

    public function destroy(Request $request, Award $award): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $tab = $award->category;
        $subtype = $award->subtype;
        $year = $award->year;
        $award->delete();

        return redirect()
            ->route('awards.index', array_filter([
                'tab' => $tab,
                'subtype' => $subtype,
                'year' => $year,
            ]))
            ->with('success', 'Устгалаа.');
    }

    public function export(Request $request, XlsxTableWriter $xlsx): HttpResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $tab = $this->normalizeTab((string) $request->query('tab', 'state_high'));
        $year = $this->normalizeYear($request->query('year'));
        $subtype = $this->normalizeSubtypeFilter($tab, (string) $request->query('subtype', ''));

        $query = Award::query()->orderByDesc('year')->orderBy('id')->where('category', $tab);

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($subtype !== '') {
            $query->where('subtype', $subtype);
        }

        $rows = $query->limit(2000)->get()->values()
            ->map(fn (Award $award, int $i) => $this->serialize($award, $i + 1));

        $columns = $this->columnsFor($tab);
        $headings = array_map(fn ($c) => $c['label'], $columns);
        $sheetRows = [];

        foreach ($rows as $row) {
            $sheetRows[] = array_map(
                fn ($c) => (string) ($row[$c['key']] ?? ''),
                $columns,
            );
        }

        $title = $this->exportTitle($tab, $subtype, $year);
        $tmp = tempnam(sys_get_temp_dir(), 'award_export_');

        try {
            $path = $tmp.'.xlsx';
            $xlsx->write($path, $title, $headings, $sheetRows);
            $content = (string) file_get_contents($path);
            @unlink($path);
        } finally {
            @unlink($tmp);
        }

        $fileName = $title.' '.now()->format('Y-m-d').'.xlsx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"awards.xlsx\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Award $existing = null): array
    {
        $category = (string) $request->input('category', $existing?->category ?? 'state_high');
        if (! array_key_exists($category, Award::CATEGORIES)) {
            $category = 'state_high';
        }

        $allowedSubtypes = Award::CATEGORY_SUBTYPES[$category] ?? [];

        $rules = [
            'category' => ['required', Rule::in(array_keys(Award::CATEGORIES))],
            'subtype' => empty($allowedSubtypes)
                ? ['nullable']
                : ['required', Rule::in($allowedSubtypes)],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'surname' => ['nullable', 'string', 'max:255'],
            'given_name' => ['nullable', 'string', 'max:255'],
            'register_no' => ['nullable', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'gender' => ['nullable', 'string', 'max:20'],
            'nominated_award' => ['nullable', 'string', 'max:255'],
            'years_in_country' => ['nullable', 'integer', 'min:0', 'max:80'],
            'years_in_sector' => ['nullable', 'integer', 'min:0', 'max:80'],
            'award_date' => ['nullable', 'date'],
            'resolution_number' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:5000'],
            'last_award' => ['nullable', 'string', 'max:255'],
            'supporting_org' => ['nullable', 'string', 'max:255'],
            'presidential_letter' => ['nullable', 'string', 'max:5000'],
            'award_name' => ['nullable', 'string', 'max:255'],
            'work_sector' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:5000'],
            'total_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'position_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'order_ref' => ['nullable', 'string', 'max:255'],
            'award_note' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        $data = $request->validate($rules);
        $data['category'] = $category;

        if (empty($allowedSubtypes)) {
            $data['subtype'] = null;
        }

        if (empty($data['year']) && ! empty($data['award_date'])) {
            $data['year'] = (int) date('Y', strtotime((string) $data['award_date']));
        }

        if (empty($data['year'])) {
            $data['year'] = (int) date('Y');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Award $award, int $no): array
    {
        return [
            'id' => $award->id,
            'no' => $no,
            'category' => $award->category,
            'category_label' => $award->categoryLabel(),
            'subtype' => $award->subtype,
            'subtype_label' => $award->subtypeLabel(),
            'year' => $award->year,
            'surname' => $award->surname,
            'given_name' => $award->given_name,
            'full_name' => $award->fullName(),
            'register_no' => $award->register_no,
            'age' => $award->age,
            'gender' => $award->gender,
            'nominated_award' => $award->nominated_award,
            'years_in_country' => $award->years_in_country,
            'years_in_sector' => $award->years_in_sector,
            'award_date' => optional($award->award_date)?->format('Y-m-d'),
            'award_date_display' => optional($award->award_date)?->format('Y.m.d'),
            'resolution_number' => $award->resolution_number,
            'position' => $award->position,
            'address' => $award->address,
            'last_award' => $award->last_award,
            'supporting_org' => $award->supporting_org,
            'presidential_letter' => $award->presidential_letter,
            'award_name' => $award->award_name,
            'work_sector' => $award->work_sector,
            'job_title' => $award->job_title,
            'total_years' => $award->total_years,
            'position_years' => $award->position_years,
            'order_ref' => $award->order_ref,
            'award_note' => $award->award_note,
            'notes' => $award->notes,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function columnsFor(string $tab): array
    {
        if ($tab === 'state_high') {
            return [
                ['key' => 'no', 'label' => '№'],
                ['key' => 'nominated_award', 'label' => 'Өргөн мэдүүлсэн шагнал'],
                ['key' => 'surname', 'label' => 'Овог'],
                ['key' => 'given_name', 'label' => 'Нэр'],
                ['key' => 'register_no', 'label' => 'Регистрийн дугаар'],
                ['key' => 'age', 'label' => 'Нас'],
                ['key' => 'gender', 'label' => 'Хүйс'],
                ['key' => 'years_in_country', 'label' => 'Улсад ажилласан жил'],
                ['key' => 'years_in_sector', 'label' => 'Тухайн салбартаа ажилласан жил'],
                ['key' => 'award_date_display', 'label' => 'Огноо'],
                ['key' => 'resolution_number', 'label' => 'Тогтоолын дугаар'],
                ['key' => 'position', 'label' => 'Албан тушаал'],
                ['key' => 'address', 'label' => 'Оршин суугаа хаяг'],
                ['key' => 'last_award', 'label' => 'Сүүлд авсан шагнал, он'],
                ['key' => 'supporting_org', 'label' => 'Дэмжсэн байгууллага'],
                ['key' => 'presidential_letter', 'label' => 'ЕТГ-т уламжилсан бичгийн огноо, дугаар'],
            ];
        }

        if ($tab === 'other') {
            return [
                ['key' => 'no', 'label' => 'Д/д'],
                ['key' => 'award_name', 'label' => 'Шагналын нэр'],
                ['key' => 'surname', 'label' => 'Овог'],
                ['key' => 'given_name', 'label' => 'Нэр'],
                ['key' => 'register_no', 'label' => 'Регистр'],
                ['key' => 'work_sector', 'label' => 'Ажилладаг салбар'],
                ['key' => 'job_title', 'label' => 'Эрхэлдэг ажил, албан тушаал'],
                ['key' => 'total_years', 'label' => 'Нийт ажилласан жил'],
                ['key' => 'position_years', 'label' => 'Тухайн албан тушаалд ажилласан жил'],
                ['key' => 'order_ref', 'label' => 'Захирамж / шийдвэрийн огноо, дугаар'],
                ['key' => 'award_note', 'label' => 'Шагналын дугаар, тэмдэглэл'],
                ['key' => 'notes', 'label' => 'Тэмдэглэл'],
            ];
        }

        // governor_honor, governor_leading
        $cols = [
            ['key' => 'no', 'label' => 'Д/д'],
            ['key' => 'subtype_label', 'label' => 'Төрөл'],
            ['key' => 'surname', 'label' => 'Овог'],
            ['key' => 'given_name', 'label' => 'Нэр'],
            ['key' => 'register_no', 'label' => 'Регистр'],
            ['key' => 'work_sector', 'label' => 'Ажилладаг салбар'],
            ['key' => 'job_title', 'label' => 'Эрхэлдэг ажил, албан тушаал'],
            ['key' => 'total_years', 'label' => 'Нийт ажилласан хугацаа'],
            ['key' => 'position_years', 'label' => 'Тухайн албан тушаалд ажилласан жил'],
            ['key' => 'order_ref', 'label' => 'Захирамжийн огноо, дугаар'],
            ['key' => 'award_note', 'label' => 'Шагналын дугаар, хүлээн авсан тухай тэмдэглэл'],
        ];

        return $cols;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function subtypeOptions(string $tab): array
    {
        $keys = Award::CATEGORY_SUBTYPES[$tab] ?? [];

        return collect($keys)->map(fn ($key) => [
            'value' => $key,
            'label' => Award::SUBTYPES[$key] ?? $key,
        ])->values()->all();
    }

    private function normalizeTab(string $tab): string
    {
        return array_key_exists($tab, Award::CATEGORIES) ? $tab : 'state_high';
    }

    private function normalizeYear(mixed $year): ?int
    {
        if ($year === null || $year === '' || $year === 'all') {
            return null;
        }

        $y = (int) $year;

        return ($y >= 1990 && $y <= 2100) ? $y : null;
    }

    private function normalizeSubtypeFilter(string $tab, string $subtype): string
    {
        $allowed = Award::CATEGORY_SUBTYPES[$tab] ?? [];

        return in_array($subtype, $allowed, true) ? $subtype : '';
    }

    private function exportTitle(string $tab, string $subtype, ?int $year): string
    {
        $base = Award::CATEGORIES[$tab] ?? 'Шагнал';

        if ($subtype !== '' && isset(Award::SUBTYPES[$subtype])) {
            $base .= ' — '.Award::SUBTYPES[$subtype];
        }

        if ($year !== null) {
            $base .= ' '.$year.' он';
        }

        return $base;
    }
}
