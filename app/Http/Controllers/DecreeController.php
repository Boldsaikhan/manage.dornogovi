<?php

namespace App\Http\Controllers;

use App\Models\Decree;
use App\Models\DocumentFormat;
use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Contracts\View\View;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DecreeController extends Controller
{
    /** Бланк + төрлөөр салгах + нийт */
    private const TABS = [
        'blank' => 'Бланкны дугаар',
        'zahiramj_a' => 'Захирамж А',
        'zahiramj_b' => 'Захирамж Б',
        'tushaal_a' => 'Тушаал А',
        'tushaal_b' => 'Тушаал Б',
        'niit' => 'Нийт',
    ];

    private const KIND_TABS = [
        'zahiramj_a' => ['category' => 'zahiramj', 'kind' => 'zahiramj_a'],
        'zahiramj_b' => ['category' => 'zahiramj', 'kind' => 'zahiramj_b'],
        'tushaal_a' => ['category' => 'tushaal', 'kind' => 'tushaal_a'],
        'tushaal_b' => ['category' => 'tushaal', 'kind' => 'tushaal_b'],
    ];

    private const DOC_KINDS = ['zahiramj_a', 'zahiramj_b', 'tushaal_a', 'tushaal_b'];

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'decrees'), 403);

        $tab = $this->normalizeTab((string) $request->query('tab', 'zahiramj_a'));

        $counts = [
            'blank' => Decree::query()->where('category', 'blank')->count(),
            'zahiramj_a' => Decree::query()->where('kind', 'zahiramj_a')->count(),
            'zahiramj_b' => Decree::query()->where('kind', 'zahiramj_b')->count(),
            'tushaal_a' => Decree::query()->where('kind', 'tushaal_a')->count(),
            'tushaal_b' => Decree::query()->where('kind', 'tushaal_b')->count(),
            'niit' => Decree::query()->whereIn('kind', self::DOC_KINDS)->count(),
        ];

        $query = Decree::query()->orderBy('id');

        if ($tab === 'blank') {
            $query->where('category', 'blank');
        } elseif ($tab === 'niit') {
            $query->whereIn('kind', self::DOC_KINDS);
        } else {
            $query->where('kind', $tab);
        }

        $rows = $query
            ->limit(300)
            ->get()
            ->values()
            ->map(fn (Decree $d, int $i) => $this->serialize($d, $i + 1));

        return Inertia::render('Modules/Decrees', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
                'count' => $counts[$value] ?? 0,
            ])->values()->all(),
            'rows' => $rows,
            'people' => PhoneDirectoryEntry::peopleOptions(),
            'pendingOfficials' => $this->pendingOfficialsForTab($tab),
            'nextNumber' => isset(self::KIND_TABS[$tab]) ? $this->nextDocumentNumber($tab) : null,
            'canManage' => ModuleAccess::canManage($request->user(), 'decrees'),
        ]);
    }

    /**
     * Харагдаж байгаа хүснэгтийг хэвлэх хуудас.
     */
    public function print(Request $request): View
    {
        abort_unless(ModuleAccess::canView($request->user(), 'decrees'), 403);

        $tab = $this->normalizeTab((string) $request->query('tab', 'zahiramj_a'));

        $query = Decree::query()->orderBy('id');

        if ($tab === 'blank') {
            $query->where('category', 'blank');
        } elseif ($tab === 'niit') {
            $query->whereIn('kind', self::DOC_KINDS);
        } else {
            $query->where('kind', $tab);
        }

        $rows = $query->limit(1000)->get()->values()
            ->map(fn (Decree $d, int $i) => $this->serialize($d, $i + 1));

        return view('decrees.print', [
            'tab' => $tab,
            'rows' => $rows,
            'title' => $this->printTitle($tab),
            'titleLabel' => match (true) {
                $tab === 'niit' => 'Гарчиг / тэргүү',
                str_starts_with($tab, 'zahiramj') => 'Захирамжийн тэргүү',
                default => 'Тушаалын гарчиг',
            },
            'format' => DocumentFormat::defaultFormat(),
        ]);
    }

    private function printTitle(string $tab): string
    {
        return match ($tab) {
            'blank' => 'Хэвлэмэл хуудасны бүртгэл',
            'zahiramj_a' => 'Аймгийн Засаг даргын Захирамжийн бүртгэл (А)',
            'zahiramj_b' => 'Аймгийн Засаг даргын Захирамжийн бүртгэл (Б)',
            'tushaal_a' => 'Тамгын газрын даргын Тушаалын бүртгэл (А)',
            'tushaal_b' => 'Тамгын газрын даргын Тушаалын бүртгэл (Б)',
            default => 'Захирамж, тушаалын нэгдсэн бүртгэл',
        };
    }

    /**
     * Хэвлэмэл хуудасны бүлгүүд — нэг мөрд зөвхөн нэг бүлэг бөглөгдөнө.
     */
    private const BLANK_GROUPS = [
        'zahiramj' => ['qty_zahiramj', 'qty_zahiramj_mn'],
        'tushaal' => ['qty_tushaal', 'qty_tushaal_mn'],
        'assignment' => ['qty_assignment', 'qty_assignment_mn'],
        'council' => ['qty_council', 'qty_council_mn'],
    ];

    /**
     * Бөглөгдсөн бүлгийг тогтооно (аль нэг тоо > 0 байвал).
     *
     * @param  array<string, mixed>  $values
     */
    private function activeBlankGroup(array $values): ?string
    {
        foreach (self::BLANK_GROUPS as $group => $fields) {
            foreach ($fields as $field) {
                if ((int) ($values[$field] ?? 0) > 0) {
                    return $group;
                }
            }
        }

        return null;
    }

    /**
     * «1402-1408» гэх мэт мужаас хамгийн сүүлийн дугаарыг гаргана.
     */
    private function rangeEnd(?string $range): int
    {
        preg_match_all('/\d+/', (string) $range, $matches);

        $numbers = array_map('intval', $matches[0] ?? []);

        return $numbers ? max($numbers) : 0;
    }

    /**
     * Тухайн төрлийн дараагийн чөлөөтэй дугаараас эхлэх мужийг тооцно.
     */
    private function nextBlankRange(string $column, int $count, ?int $ignoreId): ?string
    {
        if ($count < 1) {
            return null;
        }

        $used = Decree::query()
            ->where('category', 'blank')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck($column)
            ->map(fn (?string $range) => $this->rangeEnd($range))
            ->max();

        $start = ((int) $used) + 1;
        $end = $start + $count - 1;

        return $count === 1 ? (string) $start : $start.'-'.$end;
    }

    /**
     * Тоо ширхэгээс хамааруулж хэвлэмэл хуудасны дугаарыг бодож бичнэ.
     *
     * @param  array<string, mixed>  $data  Хадгалагдах утгууд (өөрчлөгдөнө)
     */
    private function applyBlankNumbering(array $data, ?Decree $decree = null): array
    {
        $values = [];

        foreach (self::BLANK_GROUPS as $fields) {
            foreach ($fields as $field) {
                $values[$field] = array_key_exists($field, $data)
                    ? (int) ($data[$field] ?? 0)
                    : (int) ($decree->{$field} ?? 0);
            }
        }

        // Нэг мөрд нэг бүлэг — бусад бүлгийг цэвэрлэнэ.
        $group = $this->activeBlankGroup($values);

        foreach (self::BLANK_GROUPS as $name => $fields) {
            foreach ($fields as $field) {
                if ($group !== null && $name !== $group) {
                    $values[$field] = 0;
                }

                $data[$field] = $values[$field];
            }
        }

        $countZ = $values['qty_zahiramj'] + $values['qty_zahiramj_mn'];
        $countT = $values['qty_tushaal'] + $values['qty_tushaal_mn'];

        // Гараар дугаар оруулсан бол хүндэтгэнэ (эхлэх дугаарыг тохируулах боломж).
        $manualZ = filled($data['num_zahiramj'] ?? null);
        $manualT = filled($data['num_tushaal'] ?? null);

        if (! $manualZ) {
            $data['num_zahiramj'] = $this->nextBlankRange('num_zahiramj', $countZ, $decree?->id);
        }

        if (! $manualT) {
            $data['num_tushaal'] = $this->nextBlankRange('num_tushaal', $countT, $decree?->id);
        }
        $data['blank_number'] = $data['num_zahiramj'] ?: $data['num_tushaal'] ?: null;

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);

        $tab = $this->normalizeTab((string) $request->input('tab', 'zahiramj_a'));

        if ($tab === 'niit') {
            return redirect()
                ->route('decrees.index', ['tab' => 'niit'])
                ->withErrors(['tab' => 'Нийт таб дээр мөр нэмэхийн тулд төрлийн таб сонгоно уу.']);
        }

        if ($tab === 'blank') {
            $data = $request->validate([
                'person_name' => ['nullable', 'string', 'max:255'],
                'issued_on' => ['nullable', 'date'],
                'qty_zahiramj' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_zahiramj_mn' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_tushaal' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_tushaal_mn' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_assignment' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_assignment_mn' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_council' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_council_mn' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'num_zahiramj' => ['nullable', 'string', 'max:100'],
                'num_tushaal' => ['nullable', 'string', 'max:100'],
                'void_zahiramj' => ['nullable', 'string', 'max:100'],
                'void_tushaal' => ['nullable', 'string', 'max:100'],
                'body' => ['nullable', 'string', 'max:5000'],
            ]);

            $person = PersonName::short(trim((string) ($data['person_name'] ?? '')));

            // Тоо ширхэгээс хамааруулж хэвлэмэл хуудасны дугаарыг бодно.
            $data = $this->applyBlankNumbering($data);

            Decree::query()->create([
                ...$data,
                'person_name' => $person !== '' ? $person : null,
                'issued_on' => $data['issued_on'] ?? null,
                'category' => 'blank',
                'kind' => 'blank',
                'title' => $person !== '' ? $person : '',
                'number' => null,
                'created_by' => $request->user()->id,
            ]);
        } else {
            $meta = self::KIND_TABS[$tab];

            $data = $request->validate([
                'number' => ['nullable', 'string', 'max:100'],
                'title' => ['nullable', 'string', 'max:1000'],
                'issued_on' => ['nullable', 'date'],
                'page_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'attachment_name' => ['nullable', 'string', 'max:500'],
                'attachment_pages' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'person_name' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:20000'],
            ]);

            $person = PersonName::short(trim((string) ($data['person_name'] ?? '')));

            Decree::query()->create([
                'category' => $meta['category'],
                'kind' => $meta['kind'],
                'number' => ($data['number'] ?? null) ?: $this->nextDocumentNumber($tab),
                'title' => $data['title'] ?? '',
                'issued_on' => $data['issued_on'] ?? null,
                'page_count' => $data['page_count'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'attachment_pages' => $data['attachment_pages'] ?? null,
                'person_name' => $person !== '' ? $person : null,
                'body' => $data['body'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('decrees.index', ['tab' => $tab])
            ->with('success', 'Мөр нэмлээ.');
    }

    public function update(Request $request, Decree $decree): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);

        if ($decree->category === 'blank' || $decree->kind === 'blank') {
            $data = $request->validate([
                'person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'issued_on' => ['sometimes', 'nullable', 'date'],
                'qty_zahiramj' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_zahiramj_mn' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_tushaal' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_tushaal_mn' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_assignment' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_assignment_mn' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_council' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'qty_council_mn' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'num_zahiramj' => ['sometimes', 'nullable', 'string', 'max:100'],
                'num_tushaal' => ['sometimes', 'nullable', 'string', 'max:100'],
                'void_zahiramj' => ['sometimes', 'nullable', 'string', 'max:100'],
                'void_tushaal' => ['sometimes', 'nullable', 'string', 'max:100'],
                'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ]);

            if (array_key_exists('person_name', $data)) {
                $person = PersonName::short(trim((string) ($data['person_name'] ?? '')));
                $data['person_name'] = $person !== '' ? $person : null;
                $data['title'] = $data['person_name'] ?? '';
            }

            if (array_key_exists('issued_on', $data) && ($data['issued_on'] === '' || $data['issued_on'] === null)) {
                $data['issued_on'] = null;
            }



            foreach ([
                'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
                'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
            ] as $qtyField) {
                if (array_key_exists($qtyField, $data) && $data[$qtyField] === null) {
                    $data[$qtyField] = 0;
                }
            }

            // Тоо ширхэг өөрчлөгдвөл бүлгийн онцгой байдал болон дугаарлалтыг дахин бодно.
            $qtyTouched = (bool) array_intersect(array_keys($data), [
                'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
                'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
                'num_zahiramj', 'num_tushaal',
            ]);

            if ($qtyTouched) {
                $data = $this->applyBlankNumbering($data, $decree);
            }

            $decree->update($data);
        } else {
            $data = $request->validate([
                'kind' => ['sometimes', 'nullable', Rule::in(self::DOC_KINDS)],
                'number' => ['sometimes', 'nullable', 'string', 'max:100'],
                'title' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'issued_on' => ['sometimes', 'nullable', 'date'],
                'page_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'attachment_name' => ['sometimes', 'nullable', 'string', 'max:500'],
                'attachment_pages' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            ]);

            // Төрөл нь NOT NULL — хоосон болговол хуучин утгыг үлдээнэ.
            if (array_key_exists('kind', $data) && ($data['kind'] === null || $data['kind'] === '')) {
                unset($data['kind']);
            }

            if (array_key_exists('kind', $data) && isset(self::KIND_TABS[$data['kind']])) {
                $data['category'] = self::KIND_TABS[$data['kind']]['category'];
            }

            if (array_key_exists('person_name', $data)) {
                $person = PersonName::short(trim((string) ($data['person_name'] ?? '')));
                $data['person_name'] = $person !== '' ? $person : null;
            }

            if (array_key_exists('issued_on', $data) && ($data['issued_on'] === '' || $data['issued_on'] === null)) {
                $data['issued_on'] = null;
            }

            $decree->update($data);
        }

        return back(303)->with('success', 'Хадгаллаа.');
    }

    public function destroy(Request $request, Decree $decree): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);

        $tab = $this->tabForDecree($decree);
        $this->deleteImageFile($decree);
        $decree->delete();

        return redirect()
            ->route('decrees.index', ['tab' => $tab])
            ->with('success', 'Устгалаа.');
    }

    public function uploadImage(Request $request, Decree $decree): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);
        abort_if($decree->category === 'blank' || $decree->kind === 'blank', 422);

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [
            'image.max' => 'Зургийн хэмжээ 2MB-аас хэтрэхгүй байх ёстой.',
            'image.image' => 'Зөвхөн зураг файл оруулна уу.',
        ]);

        $this->deleteImageFile($decree);

        $path = $request->file('image')->store('decrees/'.$decree->id, 'local');
        $decree->update(['file_path' => $path]);

        return back(303)->with('success', 'Зураг хадгаллаа.');
    }

    public function showImage(Request $request, Decree $decree): StreamedResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'decrees'), 403);
        abort_unless($decree->file_path && Storage::disk('local')->exists($decree->file_path), 404);

        return Storage::disk('local')->response(
            $decree->file_path,
            basename($decree->file_path),
            ['Content-Disposition' => 'inline']
        );
    }

    public function destroyImage(Request $request, Decree $decree): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);

        $this->deleteImageFile($decree);
        $decree->update(['file_path' => null]);

        return back(303)->with('success', 'Зураг устгалаа.');
    }

    private function deleteImageFile(Decree $decree): void
    {
        if ($decree->file_path && Storage::disk('local')->exists($decree->file_path)) {
            Storage::disk('local')->delete($decree->file_path);
        }
    }

    private function normalizeTab(string $tab): string
    {
        // Хуучин URL-уудыг шилжүүлэх
        return match ($tab) {
            'all' => 'niit',
            'zahiramj' => 'zahiramj_a',
            'tushaal' => 'tushaal_a',
            default => array_key_exists($tab, self::TABS) ? $tab : 'zahiramj_a',
        };
    }

    private function tabForDecree(Decree $decree): string
    {
        if ($decree->category === 'blank' || $decree->kind === 'blank') {
            return 'blank';
        }

        if (array_key_exists((string) $decree->kind, self::KIND_TABS)) {
            return (string) $decree->kind;
        }

        return 'niit';
    }

    /**
     * Тухайн төрлийн дараагийн дугаар (ж: 01, 02).
     */
    private function nextDocumentNumber(string $kind): string
    {
        $max = 0;

        Decree::query()
            ->where('kind', $kind)
            ->whereNotNull('number')
            ->where('number', '!=', '')
            ->pluck('number')
            ->each(function (string $number) use (&$max) {
                if (preg_match('/^(\d+)/', trim($number), $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Бланк авсан боловч тухайн төрлийн баримт гаргаагүй албан хаагчид.
     *
     * @return array<int, array{value: string, label: string, hint: string, org: string, category: string}>
     */
    private function pendingOfficialsForTab(string $tab): array
    {
        if ($tab === 'blank') {
            return [];
        }

        $blankQuery = Decree::query()
            ->where('category', 'blank')
            ->whereNotNull('person_name')
            ->where('person_name', '!=', '');

        if (str_starts_with($tab, 'zahiramj')) {
            $blankQuery->where(function ($query) {
                $query->where('qty_zahiramj', '>', 0)
                    ->orWhere('qty_zahiramj_mn', '>', 0)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('num_zahiramj')->where('num_zahiramj', '!=', '');
                    });
            });
        } elseif (str_starts_with($tab, 'tushaal')) {
            $blankQuery->where(function ($query) {
                $query->where('qty_tushaal', '>', 0)
                    ->orWhere('qty_tushaal_mn', '>', 0)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('num_tushaal')->where('num_tushaal', '!=', '');
                    });
            });
        }

        $blankNames = $blankQuery
            ->pluck('person_name')
            ->map(fn (?string $name) => PersonName::short(trim((string) $name)))
            ->filter(fn (string $name) => $name !== '')
            ->unique()
            ->values();

        $usedKinds = $tab === 'niit'
            ? self::DOC_KINDS
            : [$tab];

        $usedNames = Decree::query()
            ->whereIn('kind', $usedKinds)
            ->whereNotNull('person_name')
            ->where('person_name', '!=', '')
            ->pluck('person_name')
            ->map(fn (?string $name) => PersonName::short(trim((string) $name)))
            ->filter(fn (string $name) => $name !== '')
            ->unique();

        return $blankNames
            ->diff($usedNames)
            ->values()
            ->map(fn (string $name) => [
                'value' => $name,
                'label' => $name,
                'hint' => 'Бланк авсан',
                'org' => '',
                'category' => 'baiguullaga',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Decree $d, int $no): array
    {
        return [
            'id' => $d->id,
            'no' => $no,
            'category' => $d->category,
            'kind' => $d->kind,
            'kind_label' => $d->kindLabel(),
            'blank_number' => $d->blank_number,
            'number' => $d->number,
            'title' => $d->title,
            'page_count' => $d->page_count,
            'attachment_name' => $d->attachment_name,
            'attachment_pages' => $d->attachment_pages,
            'person_name' => $d->person_name,
            'qty_zahiramj' => $d->qty_zahiramj ?: '',
            'qty_zahiramj_mn' => $d->qty_zahiramj_mn ?: '',
            'qty_tushaal' => $d->qty_tushaal ?: '',
            'qty_tushaal_mn' => $d->qty_tushaal_mn ?: '',
            'qty_assignment' => $d->qty_assignment ?: '',
            'qty_assignment_mn' => $d->qty_assignment_mn ?: '',
            'qty_council' => $d->qty_council ?: '',
            'qty_council_mn' => $d->qty_council_mn ?: '',
            'num_zahiramj' => $d->num_zahiramj,
            'num_tushaal' => $d->num_tushaal,
            'void_zahiramj' => $d->void_zahiramj,
            'void_tushaal' => $d->void_tushaal,
            'issued_on' => optional($d->issued_on)?->format('Y-m-d'),
            'issued_on_display' => optional($d->issued_on)?->format('Y.m.d'),
            'body' => $d->body,
            'has_image' => (bool) $d->file_path,
            'image_url' => $d->file_path
                ? route('decrees.image.show', $d)
                : null,
        ];
    }
}
