<?php

namespace App\Http\Controllers;

use App\Models\Decree;
use App\Support\ModuleAccess;
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
            ->latest('id')
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
                'person_name' => ['required', 'string', 'max:255'],
                'issued_on' => ['required', 'date'],
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

            Decree::query()->create([
                ...$data,
                'category' => 'blank',
                'kind' => 'blank',
                'title' => $data['person_name'],
                'blank_number' => $data['num_zahiramj'] ?: $data['num_tushaal'] ?: null,
                'number' => null,
                'created_by' => $request->user()->id,
            ]);
        } else {
            $kinds = $tab === 'zahiramj'
                ? ['zahiramj_a', 'zahiramj_b']
                : ['tushaal_a', 'tushaal_b'];

            $data = $request->validate([
                'kind' => ['required', Rule::in($kinds)],
                'number' => ['required', 'string', 'max:100'],
                'title' => ['required', 'string', 'max:1000'],
                'issued_on' => ['required', 'date'],
                'page_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'attachment_name' => ['nullable', 'string', 'max:500'],
                'attachment_pages' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'person_name' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:20000'],
            ]);

            Decree::query()->create([
                'category' => $tab,
                'kind' => $data['kind'],
                'number' => $data['number'],
                'title' => $data['title'],
                'issued_on' => $data['issued_on'],
                'page_count' => $data['page_count'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'attachment_pages' => $data['attachment_pages'] ?? null,
                'person_name' => $data['person_name'] ?? null,
                'body' => $data['body'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('decrees.index', ['tab' => $tab])
            ->with('success', 'Амжилттай хадгаллаа.');
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
