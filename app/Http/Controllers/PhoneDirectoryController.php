<?php

namespace App\Http\Controllers;

use App\Models\PhoneDirectoryEntry;
use App\Services\PhoneDirectoryStaffSyncer;
use App\Support\ModuleAccess;
use App\Support\PhoneDirectoryDocxParser;
use App\Support\PhoneDirectoryDocxWriter;
use App\Support\PhoneDirectoryStaffListParser;
use App\Support\XlsxTableReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PhoneDirectoryController extends Controller
{
    private const MODULE = 'phone_directory';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);


        $entries = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = $entries
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'category' => $this->normalizeDirectoryCategory($rows->first()->category ?? null),
                'rows' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'id' => $row->id,
                    'person_name' => $row->person_name,
                    'position' => $row->position,
                    'office_phone' => $row->office_phone,
                    'mobile_phone' => $row->mobile_phone,
                ])->values(),
            ])
            ->values();


        $departmentUnits = $entries
            ->groupBy('org_name')
            ->filter(fn ($rows, $orgName) => PhoneDirectoryEntry::looksLikeDepartment($orgName)
                || ($rows->first()->category ?? null) === 'heltes')
            ->keys()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Modules/PhoneDirectory', [
            'groups' => $groups,
            'total' => $entries->count(),
            'orgNames' => $entries->pluck('org_name')->unique()->values(),
            'categories' => PhoneDirectoryEntry::CATEGORIES,
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
        ]);
    }

    public function export(Request $request, PhoneDirectoryDocxWriter $writer): HttpResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $groups = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'rows' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'person_name' => $row->person_name,
                    'position' => $row->position,
                    'office_phone' => $row->office_phone,
                    'mobile_phone' => $row->mobile_phone,
                ])->all(),
            ])
            ->values()
            ->all();

        $tmp = tempnam(sys_get_temp_dir(), 'phones');
        $writer->write($groups, $tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        $fileName = 'Утасны жагсаалт '.now()->format('Y-m-d').'.docx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"phone-directory.docx\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(array_keys(PhoneDirectoryEntry::CATEGORIES))],
            'person_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'office_phone' => ['nullable', 'string', 'max:64'],
            'mobile_phone' => ['nullable', 'string', 'max:64'],
            'before_org_name' => ['nullable', 'string', 'max:255'],
        ]);

        $beforeOrg = trim((string) ($data['before_org_name'] ?? ''));
        unset($data['before_org_name']);

        if (($data['category'] ?? '') === '') {
            // Формоос «Сонголтгүй» — байгууллагын одоогийн ангиллыг авна (байхгүй бол null).
            $data['category'] = PhoneDirectoryEntry::query()
                ->where('org_name', $data['org_name'])
                ->whereNotNull('category')
                ->value('category');
        }

        $sibling = PhoneDirectoryEntry::query()->where('org_name', $data['org_name']);
        $isNewOrg = ! (clone $sibling)->exists();

        // Дунд нь шинэ хүснэгт — заасан бүлгийн өмнө байрлуулж, доод бүлгүүдийг ухраана.
        $beforeOrder = $isNewOrg && $beforeOrg !== ''
            ? PhoneDirectoryEntry::query()->where('org_name', $beforeOrg)->min('org_order')
            : null;

        if ($beforeOrder !== null) {
            DB::transaction(function () use ($data, $beforeOrder) {
                PhoneDirectoryEntry::query()
                    ->where('org_order', '>=', (int) $beforeOrder)
                    ->increment('org_order');

                PhoneDirectoryEntry::create($data + [
                    'org_order' => (int) $beforeOrder,
                    'sort_order' => 0,
                ]);
            });

            return redirect()
                ->route('phone-directory.index')
                ->with('success', 'Шинэ хүснэгт нэмэгдлээ.');
        }

        $data['org_order'] = (int) ((clone $sibling)->value('org_order')
            ?? (PhoneDirectoryEntry::query()->max('org_order') + 1));
        $data['sort_order'] = (int) (clone $sibling)->max('sort_order') + 1;

        PhoneDirectoryEntry::create($data);

        return redirect()
            ->route('phone-directory.index')
            ->with('success', 'Бүртгэл нэмэгдлээ.');
    }

    public function update(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(array_keys(PhoneDirectoryEntry::CATEGORIES))],
            'person_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'office_phone' => ['nullable', 'string', 'max:64'],
            'mobile_phone' => ['nullable', 'string', 'max:64'],
        ]);

        if (($data['category'] ?? '') === '') {
            $data['category'] = null;
        }

        $orgChanged = $data['org_name'] !== $entry->org_name;

        if ($orgChanged) {
            $sibling = PhoneDirectoryEntry::query()->where('org_name', $data['org_name']);
            $data['org_order'] = (int) ((clone $sibling)->value('org_order')
                ?? (PhoneDirectoryEntry::query()->max('org_order') + 1));
            $data['sort_order'] = (int) (clone $sibling)->max('sort_order') + 1;
        }

        $entry->update($data);

        // Ангилал солигдвол тухайн байгууллагын бүх мөрийг ижил ангилалд байлгана.
        PhoneDirectoryEntry::query()
            ->where('org_name', $data['org_name'])
            ->update(['category' => $data['category']]);

        return redirect()
            ->route('phone-directory.index')
            ->with('success', 'Бүртгэл шинэчлэгдлээ.');
    }

    /**
     * Байгууллагын ангиллыг (агентлаг/сум/байгууллага) бүлгээр нь солино.
     */
    public function updateCategory(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $request->merge([
            'category' => $request->input('category') === '' || $request->input('category') === null
                ? null
                : $request->input('category'),
        ]);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(array_keys(PhoneDirectoryEntry::CATEGORIES))],
        ]);

        PhoneDirectoryEntry::query()
            ->where('org_name', $data['org_name'])
            ->update(['category' => $data['category'] ?? null]);

        $label = isset($data['category'])
            ? (PhoneDirectoryEntry::CATEGORIES[$data['category']] ?? $data['category'])
            : 'Сонголтгүй';

        return back(303)->with('success', $data['org_name'].' — '.$label);
    }

    /**
     * Хэлтэс/байгууллагыг бүлгээр нь дээш-доош зөөнө.
     *
     * direction = up|down — хөрш бүлэгтэй солино.
     * before_org_name — заасан бүлгийн өмнө тавина (хоосон бол хамгийн ард).
     */
    public function reorder(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'direction' => ['nullable', Rule::in(['up', 'down'])],
            'before_org_name' => ['nullable', 'string', 'max:255'],
        ]);

        $order = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('org_name')
            ->unique()
            ->values()
            ->all();

        $from = array_search($data['org_name'], $order, true);

        if ($from === false) {
            return back(303);
        }

        array_splice($order, $from, 1);

        if (! empty($data['direction'])) {
            $to = $data['direction'] === 'up'
                ? max(0, $from - 1)
                : min(count($order), $from + 1);
        } else {
            $before = trim((string) ($data['before_org_name'] ?? ''));
            $found = $before === '' ? false : array_search($before, $order, true);
            $to = $found === false ? count($order) : $found;
        }

        if ($to === $from) {
            return back(303);
        }

        array_splice($order, $to, 0, [$data['org_name']]);

        DB::transaction(function () use ($order) {
            foreach ($order as $index => $name) {
                PhoneDirectoryEntry::query()
                    ->where('org_name', $name)
                    ->update(['org_order' => $index + 1]);
            }
        });

        return back(303)->with('success', $data['org_name'].' — байрлал шинэчлэгдлээ.');
    }

    /**
     * Албан хаагчийг бүлэг дотор эсвэл өөр хэлтэс/байгууллага руу зөөнө.
     *
     * before_id — заасан мөрийн өмнө тавина (хоосон бол бүлгийн ард).
     */
    public function reorderRow(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:phone_directory_entries,id'],
            'org_name' => ['required', 'string', 'max:255'],
            'before_id' => ['nullable', 'integer'],
        ]);

        $entry = PhoneDirectoryEntry::findOrFail($data['id']);
        $org = $data['org_name'];

        $target = PhoneDirectoryEntry::query()->where('org_name', $org);

        // Хүлээн авах бүлгийн байрлал, ангиллыг өвлүүлнэ.
        $orgOrder = (int) ((clone $target)->min('org_order')
            ?? (PhoneDirectoryEntry::query()->max('org_order') + 1));
        $category = (clone $target)->whereNotNull('category')->value('category');

        $ids = (clone $target)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $entry->id));

        $before = $data['before_id'] ?? null;
        $pos = $before === null ? false : array_search((int) $before, $ids, true);
        array_splice($ids, $pos === false ? count($ids) : $pos, 0, [$entry->id]);

        DB::transaction(function () use ($entry, $org, $orgOrder, $category, $ids) {
            $entry->update([
                'org_name' => $org,
                'category' => $category,
                'org_order' => $orgOrder,
            ]);

            foreach ($ids as $index => $id) {
                PhoneDirectoryEntry::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1, 'org_order' => $orgOrder]);
            }
        });

        return back(303)->with('success', $entry->person_name.' — '.$org);
    }

    public function destroy(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $entry->delete();

        return back(303)->with('success', 'Устгалаа.');
    }

    public function import(Request $request, PhoneDirectoryDocxParser $parser): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());
        $path = $request->file('file')->getRealPath();

        if (in_array($extension, ['xlsx', 'xlsm'], true)) {
            try {
                $raw = app(XlsxTableReader::class)->rows($path);
            } catch (RuntimeException $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            } catch (Throwable) {
                return back()->withErrors(['file' => 'Excel файлыг уншиж чадсангүй. .xlsx хэлбэрээр хадгалж дахин оруулна уу.']);
            }

            $staffParser = app(PhoneDirectoryStaffListParser::class);

            if (! $staffParser->looksLikeStaffList($raw)) {
                return back()->withErrors(['file' => 'Excel-ийн эхний мөр «Овог / Нэр / Гар утас / И-мэйл» байх ёстой.']);
            }

            $result = app(PhoneDirectoryStaffSyncer::class)->sync($staffParser->fromRows($raw));

            return redirect()
                ->route('phone-directory.index')
                ->with('success', sprintf(
                    'Excel-ээс таарууллаа: %d шинэчилсэн, %d нэмсэн, %d хэрэглэгч.',
                    $result['updated'],
                    $result['created'],
                    $result['users'],
                ));
        }

        if (! in_array($extension, ['docx', 'docm'], true)) {
            return back()->withErrors(['file' => 'Зөвхөн .docx эсвэл .xlsx файл дэмжинэ.']);
        }

        try {
            $rows = $parser->parse($path);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['file' => 'Word файлыг уншиж чадсангүй. .docx хэлбэрээр хадгалж дахин оруулна уу.']);
        }

        if (! $rows) {
            return back()->withErrors(['file' => 'Файлаас хүснэгт олдсонгүй. Толгой нь № / Овог нэр / Албан тушаал / Ажлын өрөөний утас / Гар утас байх ёстой.']);
        }

        $replace = $request->boolean('replace');

        DB::transaction(function () use ($rows, $replace) {
            if ($replace) {
                PhoneDirectoryEntry::query()->delete();
                $offset = 0;
            } else {
                $offset = (int) PhoneDirectoryEntry::query()->max('org_order');
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                $now = now();

                PhoneDirectoryEntry::insert(array_map(fn (array $row) => [
                    'org_name' => $row['org_name'],
                    'category' => PhoneDirectoryEntry::guessCategory($row['org_name']),
                    'org_order' => $row['org_order'] + $offset,
                    'sort_order' => $row['sort_order'],
                    'person_name' => $row['person_name'],
                    'position' => $row['position'],
                    'office_phone' => $row['office_phone'],
                    'mobile_phone' => $row['mobile_phone'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return redirect()
            ->route('phone-directory.index')
            ->with('success', count($rows).' мөр импортлолоо.');
    }

    /** Танигдахгүй төлвийг «сонголтгүй» гэж харуулна. */
    private function normalizeDirectoryCategory(?string $category): string
    {
        if ($category === null || $category === '') {
            return '';
        }

        return array_key_exists($category, PhoneDirectoryEntry::CATEGORIES)
            ? $category
            : '';
    }
}
