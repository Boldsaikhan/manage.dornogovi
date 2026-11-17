<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AwardType;
use App\Support\AwardImporter;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use App\Support\MongolianRegister;
use App\Support\TabularFileReader;
use App\Support\XlsxTableWriter;
use Illuminate\Http\JsonResponse;
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
        $subtype = $this->normalizeSubtypeFilter($tab, (string) $request->query('subtype', ''));

        $years = Award::query()
            ->where('category', $tab)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        // Он заагаагүй бол сүүлийн он — 3000+ мөрийг нэг дор ачаалахгүй.
        $rawYear = $request->query('year');
        $yearAll = $rawYear === 'all';
        if ($rawYear === null || $rawYear === '') {
            $year = $years[0] ?? (int) date('Y');
        } else {
            $year = $this->normalizeYear($rawYear);
        }

        $countsQuery = Award::query();
        ModuleOwnScope::apply($countsQuery, $request->user(), self::MODULE);

        $counts = $countsQuery
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $query = Award::query()->orderByDesc('id');
        $query->where('category', $tab);

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($subtype !== '') {
            $query->where('subtype', $subtype);
        }

        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        $totalMatched = (clone $query)->count();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 100;
        $offset = 0;

        if ($yearAll) {
            // «Бүгд» — хуудас бүрт 100 мөр (DOM гацаанаас сэргийлнэ).
            $collection = $query->forPage($page, $perPage)->get()->values();
            $offset = ($page - 1) * $perPage;
        } else {
            $collection = $query->limit(2000)->get()->values();
            $page = 1;
            $perPage = max($collection->count(), 1);
        }

        $rows = $collection->map(
            fn (Award $award, int $i) => $this->serialize($award, $totalMatched - $offset - $i),
        );

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
            'yearAll' => $yearAll,
            'years' => $years,
            'page' => $page,
            'perPage' => $perPage,
            'totalMatched' => $totalMatched,
            'columns' => $this->columnsFor($tab),
            'rows' => $rows,
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
            'categories' => Award::CATEGORIES,
            'allSubtypes' => Award::SUBTYPES,
            'categorySubtypes' => Award::CATEGORY_SUBTYPES,
            'awardTypes' => AwardType::options(),
            // in_use тооцоо хүнд — зөвхөн төрлийн цонх нээхэд хэрэгтэй тул хөнгөнөөр өгнө.
            'awardTypeRows' => $this->awardTypeRows(light: true),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool, sort_order: int, in_use: bool}>
     */
    private function awardTypeRows(bool $light = false): array
    {
        $used = $light ? collect() : AwardType::usedNames();

        return AwardType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'sort_order'])
            ->map(fn (AwardType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'is_active' => (bool) $type->is_active,
                'sort_order' => (int) $type->sort_order,
                'in_use' => $used->has($type->name),
            ])
            ->values()
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $this->validated($request);
        ModuleOwnScope::assertCanCreate($request->user(), self::MODULE, $data);
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
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $award), 403);

        $data = $this->validatedPartial($request, $award);
        $award->update($data);

        return back();
    }

    public function destroy(Request $request, Award $award): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $award), 403);

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

    public function importPreview(Request $request, TabularFileReader $reader, AwardImporter $importer): JsonResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'tab' => ['nullable', 'string'],
        ]);

        $file = $data['file'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['xlsx', 'xlsm'], true), 422, 'Зөвхөн .xlsx файл дэмжинэ.');

        $tab = $this->normalizeTab((string) ($data['tab'] ?? 'state_high'));
        $path = $file->getRealPath();
        abort_unless(is_string($path) && is_file($path), 422, 'Файлыг уншиж чадсангүй.');

        return response()->json($importer->preview($reader->rows($path, $extension), $this->columnsFor($tab)));
    }

    public function importStore(Request $request, AwardImporter $importer): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'tab' => ['required', 'string'],
            'subtype' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'entries' => ['required', 'array', 'max:400'],
            'entries.*' => ['array'],
        ]);

        $tab = $this->normalizeTab($data['tab']);
        $subtype = $this->normalizeSubtypeFilter($tab, (string) ($data['subtype'] ?? ''));
        $year = $this->normalizeYear($data['year'] ?? null) ?? (int) date('Y');

        ModuleOwnScope::assertCanCreate($request->user(), self::MODULE, [
            'category' => $tab,
            'subtype' => $subtype !== '' ? $subtype : null,
        ]);

        $created = $importer->store($data['entries'], [
            'category' => $tab,
            'subtype' => $subtype !== '' ? $subtype : null,
            'year' => $year,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('awards.index', array_filter([
                'tab' => $tab,
                'subtype' => $subtype !== '' ? $subtype : null,
                'year' => $year,
            ]))
            ->with('success', $created > 0
                ? "{$created} мөрийг Excel-ээс орууллаа."
                : 'Оруулах мөр олдсонгүй.');
    }

    public function export(Request $request, XlsxTableWriter $xlsx): HttpResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $tab = $this->normalizeTab((string) $request->query('tab', 'state_high'));
        $year = $this->normalizeYear($request->query('year'));
        $subtype = $this->normalizeSubtypeFilter($tab, (string) $request->query('subtype', ''));

        $query = Award::query()->orderByDesc('id')->where('category', $tab);

        if ($year !== null) {
            $query->where('year', $year);
        }

        if ($subtype !== '') {
            $query->where('subtype', $subtype);
        }

        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        $collection = $query->limit(2000)->get()->values();
        $total = $collection->count();
        $rows = $collection->map(
            fn (Award $award, int $i) => $this->serialize($award, $total - $i),
        );

        $columns = $this->columnsFor($tab);
        $headings = array_map(fn ($c) => $this->columnHeading($c), $columns);
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
            'last_award_date' => ['nullable', 'date'],
            'supporting_org' => ['nullable', 'string', 'max:255'],
            'forwarded_on' => ['nullable', 'date'],
            'forwarded_letter_no' => ['nullable', 'string', 'max:255'],
            'received_award' => ['nullable', 'string', 'max:255'],
            'presidential_letter' => ['nullable', 'string', 'max:5000'],
            'award_name' => ['nullable', 'string', 'max:255'],
            'work_sector' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
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

        return $this->applyRegisterDerived($data, (int) $data['year']);
    }

    /**
     * Хүснэгтийн нүд бүрээр шинэчлэх (patch).
     *
     * @return array<string, mixed>
     */
    private function validatedPartial(Request $request, Award $award): array
    {
        $allowedSubtypes = Award::CATEGORY_SUBTYPES[$award->category] ?? [];

        $rules = [
            'category' => ['sometimes', Rule::in(array_keys(Award::CATEGORIES))],
            'subtype' => ['sometimes', 'nullable', Rule::in($allowedSubtypes ?: array_keys(Award::SUBTYPES))],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1990', 'max:2100'],
            'surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'given_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'register_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nominated_award' => ['sometimes', 'nullable', 'string', 'max:255'],
            'years_in_country' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'years_in_sector' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'award_date' => ['sometimes', 'nullable', 'date'],
            'resolution_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'last_award' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_award_date' => ['sometimes', 'nullable', 'date'],
            'supporting_org' => ['sometimes', 'nullable', 'string', 'max:255'],
            'forwarded_on' => ['sometimes', 'nullable', 'date'],
            'forwarded_letter_no' => ['sometimes', 'nullable', 'string', 'max:255'],
            'received_award' => ['sometimes', 'nullable', 'string', 'max:255'],
            'presidential_letter' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'award_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'work_sector' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'total_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'position_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'order_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'award_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];

        $data = $request->validate($rules);

        foreach (['award_date', 'last_award_date', 'forwarded_on'] as $dateField) {
            if (array_key_exists($dateField, $data) && empty($data[$dateField])) {
                $data[$dateField] = null;
            }
        }

        if (array_key_exists('award_date', $data) && ! empty($data['award_date']) && ! array_key_exists('year', $data)) {
            $data['year'] = (int) date('Y', strtotime((string) $data['award_date']));
        }

        $year = array_key_exists('year', $data)
            ? (int) ($data['year'] ?: date('Y'))
            : (int) ($award->year ?: date('Y'));

        if (array_key_exists('register_no', $data)) {
            return $this->applyRegisterDerived($data, $year, force: true);
        }

        return $data;
    }

    /**
     * РД-аас нас, хүйс, цэвэрлэсэн регистр бөглөнө.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyRegisterDerived(array $data, int $year, bool $force = false): array
    {
        if (! array_key_exists('register_no', $data)) {
            return $data;
        }

        $parsed = MongolianRegister::parse(
            is_string($data['register_no'] ?? null) ? $data['register_no'] : null,
            $year,
        );

        if ($parsed === null) {
            return $data;
        }

        $data['register_no'] = $parsed['register'];

        if ($force || empty($data['age'])) {
            $data['age'] = $parsed['age'];
        }

        if ($force || empty($data['gender'])) {
            $data['gender'] = $parsed['gender'];
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
            'last_award_date' => optional($award->last_award_date)?->format('Y-m-d'),
            'last_award_date_display' => optional($award->last_award_date)?->format('Y.m.d'),
            'supporting_org' => $award->supporting_org,
            'forwarded_on' => optional($award->forwarded_on)?->format('Y-m-d'),
            'forwarded_on_display' => optional($award->forwarded_on)?->format('Y.m.d'),
            'forwarded_letter_no' => $award->forwarded_letter_no,
            'received_award' => $award->received_award,
            'presidential_letter' => $award->presidential_letter,
            'award_name' => $award->award_name,
            'work_sector' => $award->work_sector,
            'profession' => $award->profession,
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
                ['key' => 'no', 'label' => '№', 'readonly' => true, 'rowspan' => 2, 'width' => '2.5rem'],
                [
                    'key' => 'nominated_award',
                    'label' => 'Өргөн мэдүүлсэн шагналын төрөл',
                    'field' => 'nominated_award',
                    'input' => 'award_type',
                    'rowspan' => 2,
                    'width' => '8rem',
                ],
                ['key' => 'surname', 'label' => 'Овог', 'field' => 'surname', 'group' => 'Ерөнхий мэдээлэл', 'nowrap' => true, 'width' => '7rem'],
                ['key' => 'given_name', 'label' => 'Нэр', 'field' => 'given_name', 'group' => 'Ерөнхий мэдээлэл', 'nowrap' => true, 'width' => '7.5rem'],
                ['key' => 'register_no', 'label' => 'Регистрийн дугаар', 'field' => 'register_no', 'group' => 'Ерөнхий мэдээлэл', 'nowrap' => true, 'width' => '6.5rem'],
                ['key' => 'age', 'label' => 'Нас', 'field' => 'age', 'input' => 'number', 'readonly' => true, 'group' => 'Ерөнхий мэдээлэл', 'width' => '3rem', 'hint' => 'РД-аас'],
                ['key' => 'gender', 'label' => 'Хүйс', 'field' => 'gender', 'input' => 'gender', 'readonly' => true, 'group' => 'Ерөнхий мэдээлэл', 'width' => '3rem', 'hint' => 'РД-аас'],
                [
                    'key' => 'position',
                    'lines' => ['Ажил, албан тушаал', 'өндөр настан бол', 'үндсэн харьяалал'],
                    'field' => 'position',
                    'multiline' => true,
                    'group' => 'Ерөнхий мэдээлэл',
                    'width' => '9rem',
                ],
                ['key' => 'work_sector', 'label' => 'Харьяалах салбар', 'field' => 'work_sector', 'group' => 'Ерөнхий мэдээлэл', 'width' => '7rem'],
                ['key' => 'profession', 'label' => 'Мэргэжил', 'field' => 'profession', 'group' => 'Ерөнхий мэдээлэл', 'width' => '6rem'],
                [
                    'key' => 'years_in_country',
                    'lines' => ['Улсад', 'ажилласан', 'жил'],
                    'field' => 'years_in_country',
                    'input' => 'number',
                    'group' => 'Ерөнхий мэдээлэл',
                    'width' => '3.5rem',
                ],
                [
                    'key' => 'last_award',
                    'label' => 'Шагналын нэр',
                    'field' => 'last_award',
                    'input' => 'award_type',
                    'group' => 'Шагналын мэдээлэл (Сүүлд авсан)',
                    'width' => '7rem',
                ],
                [
                    'key' => 'last_award_date_display',
                    'label' => 'Огноо',
                    'field' => 'last_award_date',
                    'input' => 'date',
                    'group' => 'Шагналын мэдээлэл (Сүүлд авсан)',
                    'width' => '5.5rem',
                ],
                [
                    'key' => 'supporting_org',
                    'label' => 'Байгууллага',
                    'field' => 'supporting_org',
                    'group' => 'Дээд байгууллагад уламжилсан',
                    'width' => '7rem',
                ],
                [
                    'key' => 'forwarded_on_display',
                    'label' => 'Огноо',
                    'field' => 'forwarded_on',
                    'input' => 'date',
                    'group' => 'Дээд байгууллагад уламжилсан',
                    'width' => '5.5rem',
                ],
                [
                    'key' => 'forwarded_letter_no',
                    'label' => 'Албан бичгийн дугаар',
                    'field' => 'forwarded_letter_no',
                    'group' => 'Дээд байгууллагад уламжилсан',
                    'width' => '6rem',
                ],
                [
                    'key' => 'received_award',
                    'label' => 'Шагналын төрөл',
                    'field' => 'received_award',
                    'input' => 'award_type',
                    'group' => 'Авсан шагнал',
                    'width' => '7rem',
                ],
                [
                    'key' => 'award_date_display',
                    'label' => 'Зарлигийн огноо',
                    'field' => 'award_date',
                    'input' => 'date',
                    'group' => 'Авсан шагнал',
                    'width' => '5.5rem',
                ],
                [
                    'key' => 'resolution_number',
                    'label' => 'Зарлигийн дугаар',
                    'field' => 'resolution_number',
                    'group' => 'Авсан шагнал',
                    'width' => '5.5rem',
                ],
            ];
        }

        if ($tab === 'other') {
            return [
                ['key' => 'no', 'label' => 'Д/д', 'readonly' => true],
                ['key' => 'award_name', 'label' => 'Шагналын нэр', 'field' => 'award_name', 'input' => 'award_type'],
                ['key' => 'surname', 'label' => 'Овог', 'field' => 'surname', 'nowrap' => true],
                ['key' => 'given_name', 'label' => 'Нэр', 'field' => 'given_name', 'nowrap' => true],
                ['key' => 'register_no', 'label' => 'Регистр', 'field' => 'register_no', 'nowrap' => true],
                ['key' => 'work_sector', 'label' => 'Ажилладаг салбар', 'field' => 'work_sector'],
                ['key' => 'job_title', 'label' => 'Эрхэлдэг ажил, албан тушаал', 'field' => 'job_title', 'multiline' => true],
                ['key' => 'total_years', 'lines' => ['Нийт', 'ажилласан', 'жил'], 'field' => 'total_years', 'input' => 'number'],
                ['key' => 'position_years', 'lines' => ['Тухайн', 'албан', 'тушаалд', 'ажилласан', 'жил'], 'field' => 'position_years', 'input' => 'number'],
                ['key' => 'order_ref', 'lines' => ['Захирамж /', 'шийдвэрийн', 'огноо, дугаар'], 'field' => 'order_ref'],
                ['key' => 'award_note', 'lines' => ['Шагналын дугаар,', 'хүлээн авсан', 'тухай тэмдэглэл'], 'field' => 'award_note', 'multiline' => true],
                ['key' => 'notes', 'label' => 'Тэмдэглэл', 'field' => 'notes', 'multiline' => true],
            ];
        }

        // governor_honor, governor_leading — төрөл нь дээрх табаар шүүгдэнэ
        return [
            ['key' => 'no', 'label' => 'Д/д', 'readonly' => true],
            ['key' => 'surname', 'label' => 'Овог', 'field' => 'surname', 'nowrap' => true],
            ['key' => 'given_name', 'label' => 'Нэр', 'field' => 'given_name', 'nowrap' => true],
            ['key' => 'register_no', 'label' => 'Регистр', 'field' => 'register_no', 'nowrap' => true],
            ['key' => 'work_sector', 'label' => 'Ажилладаг салбар', 'field' => 'work_sector'],
            ['key' => 'job_title', 'lines' => ['Эрхэлдэг ажил,', 'албан тушаал'], 'field' => 'job_title', 'multiline' => true],
            ['key' => 'total_years', 'lines' => ['Нийт', 'ажилласан', 'хугацаа'], 'field' => 'total_years', 'input' => 'number'],
            ['key' => 'position_years', 'lines' => ['Тухайн', 'албан', 'тушаалд', 'ажилласан', 'жил'], 'field' => 'position_years', 'input' => 'number'],
            ['key' => 'order_ref', 'lines' => ['Захирамжийн', 'огноо, дугаар'], 'field' => 'order_ref'],
            ['key' => 'award_note', 'lines' => ['Шагналын дугаар,', 'хүлээн авсан', 'тухай тэмдэглэл'], 'field' => 'award_note', 'multiline' => true],
        ];
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

    /**
     * @param  array{label?: string, lines?: list<string>}  $column
     */
    private function columnHeading(array $column): string
    {
        if (! empty($column['lines'])) {
            return implode(' ', $column['lines']);
        }

        return (string) ($column['label'] ?? '');
    }
}
