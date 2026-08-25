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
    private const TABS = [
        'zahiramj' => 'Захирамж',
        'tushaal' => 'Тушаал',
        'all' => 'Нийт',
    ];

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'decrees'), 403);

        $tab = (string) $request->query('tab', 'zahiramj');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'zahiramj';
        }

        $counts = [
            'zahiramj' => Decree::query()->where('category', 'zahiramj')->count(),
            'tushaal' => Decree::query()->where('category', 'tushaal')->count(),
            'all' => Decree::query()->where('category', 'blank')->count(),
        ];

        $query = Decree::query()->latest('id')->limit(300);

        if ($tab === 'all') {
            $query->where('category', 'blank');
        } else {
            $query->where('category', $tab);
        }

        $rows = $query->get()->values()->map(fn (Decree $d, int $i) => $this->serialize($d, $i + 1));

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

        $tab = (string) $request->input('tab', 'zahiramj');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'zahiramj';
        }

        if ($tab === 'all') {
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
                'title' => ['required', 'string', 'max:500'],
                'blank_number' => ['nullable', 'string', 'max:100'],
                'issued_on' => ['nullable', 'date'],
                // Стандарт хүснэгтийн холбогдох талбарууд
                'person_name' => ['nullable', 'string', 'max:255'],
                'qty' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'qty_mn' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'sheet_number' => ['nullable', 'string', 'max:100'],
                'void_number' => ['nullable', 'string', 'max:100'],
                'body' => ['nullable', 'string', 'max:20000'],
            ]);

            // Тухайн төрлийн (захирамж/тушаал) баганад буулгана.
            $isZahiramj = $tab === 'zahiramj';

            Decree::query()->create([
                'kind' => $data['kind'],
                'number' => $data['number'],
                'title' => $data['title'],
                'blank_number' => $data['blank_number'] ?? null,
                'issued_on' => $data['issued_on'] ?? null,
                'body' => $data['body'] ?? null,
                'person_name' => $data['person_name'] ?? null,
                'qty_zahiramj' => $isZahiramj ? ($data['qty'] ?? 0) : 0,
                'qty_zahiramj_mn' => $isZahiramj ? ($data['qty_mn'] ?? 0) : 0,
                'qty_tushaal' => $isZahiramj ? 0 : ($data['qty'] ?? 0),
                'qty_tushaal_mn' => $isZahiramj ? 0 : ($data['qty_mn'] ?? 0),
                'num_zahiramj' => $isZahiramj ? ($data['sheet_number'] ?? null) : null,
                'num_tushaal' => $isZahiramj ? null : ($data['sheet_number'] ?? null),
                'void_zahiramj' => $isZahiramj ? ($data['void_number'] ?? null) : null,
                'void_tushaal' => $isZahiramj ? null : ($data['void_number'] ?? null),
                'category' => $tab,
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

        $tab = $decree->category === 'blank' ? 'all' : $decree->category;
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
            'body' => $d->body,
        ];
    }
}
