<?php

namespace App\Http\Controllers;

use App\Models\Decree;
use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DecreeController extends Controller
{
    /** Бланкны дугаар | Захирамжийн дугаар | Тушаалын дугаар */
    private const TABS = [
        'blank' => 'Бланкны дугаар',
        'zahiramj' => 'Захирамжийн дугаар',
        'tushaal' => 'Тушаалын дугаар',
    ];

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'decrees'), 403);

        $tab = (string) $request->query('tab', 'blank');
        // Хуучин «all» → бланк
        if ($tab === 'all') {
            $tab = 'blank';
        }
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'blank';
        }

        $counts = [
            'blank' => Decree::query()->where('category', 'blank')->count(),
            'zahiramj' => Decree::query()->where('category', 'zahiramj')->count(),
            'tushaal' => Decree::query()->where('category', 'tushaal')->count(),
        ];

        $rows = Decree::query()
            ->where('category', $tab)
            ->orderBy('id')
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
            'canManage' => ModuleAccess::canManage($request->user(), 'decrees'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'decrees'), 403);

        $tab = (string) $request->input('tab', 'blank');
        if ($tab === 'all') {
            $tab = 'blank';
        }
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'blank';
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

            Decree::query()->create([
                ...$data,
                'person_name' => $person !== '' ? $person : null,
                'issued_on' => $data['issued_on'] ?? null,
                'category' => 'blank',
                'kind' => 'blank',
                'title' => $person !== '' ? $person : '',
                'blank_number' => ($data['num_zahiramj'] ?? null) ?: ($data['num_tushaal'] ?? null) ?: null,
                'number' => null,
                'created_by' => $request->user()->id,
            ]);
        } else {
            $kinds = $tab === 'zahiramj'
                ? ['zahiramj_a', 'zahiramj_b']
                : ['tushaal_a', 'tushaal_b'];

            $data = $request->validate([
                'kind' => ['nullable', Rule::in($kinds)],
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
                'category' => $tab,
                'kind' => $data['kind'] ?? $kinds[0],
                'number' => $data['number'] ?? null,
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

        $tab = array_key_exists($decree->category, self::TABS) ? $decree->category : 'blank';

        if ($tab === 'blank') {
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

            if (array_key_exists('num_zahiramj', $data) || array_key_exists('num_tushaal', $data)) {
                $numZ = array_key_exists('num_zahiramj', $data)
                    ? $data['num_zahiramj']
                    : $decree->num_zahiramj;
                $numT = array_key_exists('num_tushaal', $data)
                    ? $data['num_tushaal']
                    : $decree->num_tushaal;
                $data['blank_number'] = $numZ ?: $numT ?: null;
            }

            foreach ([
                'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
                'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
            ] as $qtyField) {
                if (array_key_exists($qtyField, $data) && $data[$qtyField] === null) {
                    $data[$qtyField] = 0;
                }
            }

            $decree->update($data);
        } else {
            $kinds = $tab === 'zahiramj'
                ? ['zahiramj_a', 'zahiramj_b']
                : ['tushaal_a', 'tushaal_b'];

            $data = $request->validate([
                'kind' => ['sometimes', 'nullable', Rule::in($kinds)],
                'number' => ['sometimes', 'nullable', 'string', 'max:100'],
                'title' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'issued_on' => ['sometimes', 'nullable', 'date'],
                'page_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'attachment_name' => ['sometimes', 'nullable', 'string', 'max:500'],
                'attachment_pages' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
                'person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            ]);

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

        $tab = array_key_exists($decree->category, self::TABS) ? $decree->category : 'blank';
        $decree->delete();

        return redirect()
            ->route('decrees.index', ['tab' => $tab])
            ->with('success', 'Устгалаа.');
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
        ];
    }
}
