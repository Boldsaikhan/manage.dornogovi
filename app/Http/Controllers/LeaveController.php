<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    private const MODULE = 'leaves';

    private const SCOPES = [
        'udirdlaga' => 'Аймгийн удирдлагууд',
        'agentlag' => 'Агентлаг',
        'sum' => 'Сумд',
        'baiguullaga' => 'Байгууллага',
    ];

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $scope = (string) $request->query('scope', 'baiguullaga');
        if ($scope !== 'all' && ! array_key_exists($scope, self::SCOPES)) {
            $scope = 'baiguullaga';
        }

        $query = Leave::query()->with(['user:id,name', 'department:id,name'])->latest('id');
        if ($scope !== 'all') {
            $query->where('scope', $scope);
        }

        $counts = Leave::query()
            ->selectRaw('scope, count(*) as aggregate')
            ->groupBy('scope')
            ->pluck('aggregate', 'scope');

        $tabs = [
            ['value' => 'all', 'label' => 'Нийт', 'count' => (int) $counts->sum()],
        ];
        foreach (self::SCOPES as $key => $label) {
            $tabs[] = ['value' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        $rows = $query->limit(300)->get()->map(fn (Leave $leave) => $this->serialize($leave));

        return Inertia::render('Modules/Leaves', [
            'activeScope' => $scope,
            'tabs' => $tabs,
            'rows' => $rows,
            'directory' => $this->directory(),
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
            'scopes' => self::SCOPES,
            'types' => Leave::TYPES,
            'signers' => Leave::SIGNERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(self::SCOPES))],
            'org_name' => ['required', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'slip_number' => ['nullable', 'string', 'max:50'],
            'signer' => ['required', Rule::in(array_keys(Leave::SIGNERS))],
            'type' => ['required', Rule::in(array_keys(Leave::TYPES))],
            'start_date' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $days = (int) $data['days'];
        $end = $start->copy()->addDays($days - 1);

        Leave::query()->create([
            ...$data,
            'end_date' => $end->toDateString(),
            'status' => $data['status'] ?? 'approved',
            'user_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
        ]);

        return redirect()
            ->route('leaves.index', ['scope' => $data['scope']])
            ->with('success', 'Чөлөөний бүртгэл хадгалагдлаа.');
    }

    public function destroy(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $scope = $leave->scope ?: 'baiguullaga';
        $leave->delete();

        return redirect()
            ->route('leaves.index', ['scope' => $scope])
            ->with('success', 'Устгалаа.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Leave $leave): array
    {
        $start = $leave->start_date;

        return [
            'id' => $leave->id,
            'scope' => $leave->scope,
            'scope_label' => self::SCOPES[$leave->scope] ?? $leave->scope,
            'org_name' => $leave->org_name,
            'unit' => $this->unitName($leave->org_name),
            'person_name' => $leave->person_name ?: ($leave->user?->name ?? ''),
            'slip_number' => $leave->slip_number,
            'signer' => $leave->signer ?: 'acting',
            'type' => $leave->type,
            'type_label' => $leave->typeLabel(),
            'start_date' => optional($start)?->format('Y-m-d'),
            'end_date' => optional($leave->end_date)?->format('Y-m-d'),
            'year' => optional($start)?->format('Y'),
            'month' => optional($start)?->format('n'),
            'day' => optional($start)?->format('j'),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'slip_url' => route('leaves.slip', $leave),
        ];
    }

    private function unitName(?string $name): string
    {
        $name = trim((string) $name);

        return trim((string) preg_replace('/\s*хэлт(эс|сийн)$/ui', '', $name));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Утасны жагсаалтаас байгууллага, хүмүүсийн сонголт.
     *
     * @return array<int, array<string, mixed>>
     */
    private function directory(): array
    {
        return $this->phoneDirectoryGroups();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phoneDirectoryGroups(): array
    {
        return PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['org_name', 'category', 'person_name', 'position'])
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'category' => $rows->first()->category ?? 'baiguullaga',
                'people' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'name' => $row->person_name,
                    'position' => $row->position,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
