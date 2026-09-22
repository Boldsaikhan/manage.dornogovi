<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use App\Support\PdfTableWriter;
use App\Support\XlsxTableWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

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
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
            'scopes' => self::SCOPES,
            'types' => Leave::TYPES,
            'signers' => Leave::SIGNERS,
        ]);
    }

    /**
     * Харагдаж байгаа (эсвэл сонгосон) мөрүүдийг Excel / Word / PDF файлаар татна.
     */
    public function export(
        Request $request,
        DocxTableWriter $docx,
        XlsxTableWriter $xlsx,
        PdfTableWriter $pdf,
    ): HttpResponse {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $format = strtolower((string) $request->query('format', 'xlsx'));
        abort_unless(in_array($format, ['xlsx', 'docx', 'pdf'], true), 404);

        $scope = (string) $request->query('scope', 'baiguullaga');
        if ($scope !== 'all' && ! array_key_exists($scope, self::SCOPES)) {
            $scope = 'baiguullaga';
        }

        $query = Leave::query()->latest('id');
        if ($scope !== 'all') {
            $query->where('scope', $scope);
        }
        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        // Сонгосон мөр байвал зөвхөн түүнийг татна.
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $leaves = $query->limit(2000)->get();
        $total = $leaves->count();

        $headings = [
            'Д/д', 'Байгууллага / хэлтэс', 'Албан хаагч', 'Төрөл',
            'Эхлэх', 'Хоног', 'Дуусах', 'Үндэслэл', 'Орлон гарын үсэг зурсан',
        ];
        $widths = [500, 2600, 1800, 1200, 1100, 700, 1100, 2400, 2000];
        $center = [0, 3, 4, 5, 6];

        $rows = $leaves->values()->map(function (Leave $leave, int $index) use ($total) {
            return [
                (string) ($total - $index),
                (string) ($leave->org_name ?? ''),
                (string) ($leave->person_name ?? ''),
                $leave->typeLabel(),
                optional($leave->start_date)?->format('Y-m-d') ?? '',
                (string) ($leave->days ?? ''),
                optional($leave->end_date)?->format('Y-m-d') ?? '',
                (string) ($leave->reason ?? ''),
                Leave::SIGNERS[$leave->signer] ?? (string) $leave->signer,
            ];
        })->all();

        $title = 'Чөлөөний бүртгэл'.($scope !== 'all' ? ' — '.self::SCOPES[$scope] : '');
        $tmp = tempnam(sys_get_temp_dir(), 'leave_export_');

        try {
            if ($format === 'xlsx') {
                $path = $tmp.'.xlsx';
                $xlsx->write($path, $title, $headings, $rows);
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            } elseif ($format === 'docx') {
                $path = $tmp.'.docx';
                $docxRows = array_map(fn (array $cells) => ['type' => 'data', 'cells' => $cells], $rows);
                $docx->write($path, $title, $headings, $widths, $docxRows, $center, true);
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            } else {
                $path = $tmp.'.pdf';
                $pdf->write($path, $title, $headings, $rows, true);
                $mime = 'application/pdf';
            }

            $content = (string) file_get_contents($path);
            @unlink($path);
        } finally {
            @unlink($tmp);
        }

        $fileName = $title.' '.now()->format('Y-m-d').'.'.$format;

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"leaves.{$format}\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /**
     * Мөр үүсгэнэ.
     *
     * Хүснэгтэд «Шинэ нэмэх» дарахад бараг хоосон мөр шууд үүсээд, талбаруудыг
     * нь дараа нь {@see update()} горимоор нүд бүрээр нь бөглөдөг тул талбарууд
     * заавал биш.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'scope' => ['nullable', Rule::in(array_keys(self::SCOPES))],
            'org_name' => ['nullable', 'string', 'max:255'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'slip_number' => ['nullable', 'string', 'max:50'],
            'signer' => ['nullable', Rule::in(array_keys(Leave::SIGNERS))],
            'type' => ['nullable', Rule::in(array_keys(Leave::TYPES))],
            'start_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        ModuleOwnScope::assertCanCreate($request->user(), self::MODULE, $data);

        $scope = $data['scope'] ?? 'baiguullaga';
        $start = Carbon::parse($data['start_date'] ?? now())->startOfDay();
        $days = (int) ($data['days'] ?? 1);
        $end = $start->copy()->addDays($days - 1);

        Leave::query()->create([
            'scope' => $scope,
            'org_name' => $data['org_name'] ?? null,
            'person_name' => $data['person_name'] ?? null,
            'slip_number' => $data['slip_number'] ?? null,
            'signer' => $data['signer'] ?? 'acting',
            'type' => $data['type'] ?? 'tsalintai',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => $data['status'] ?? 'approved',
            'user_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
        ]);

        if (filled($data['person_name'] ?? null)) {
            app(\App\Services\Push\EmployeePushNotifier::class)->notifyNamed(
                $data['person_name'],
                [
                    'title' => 'Чөлөөний бүртгэл',
                    'body' => $data['person_name'].' — '.($data['type'] ?? 'чөлөө').' ('.$start->format('Y-m-d').', '.$days.' хоног)',
                    'url' => '/modules/leaves',
                    'tag' => 'leave',
                ],
            );
        }

        return redirect()
            ->route('leaves.index', ['scope' => $scope])
            ->with('success', 'Чөлөөний бүртгэл хадгалагдлаа.');
    }

    /**
     * Хүснэгтийн нүдийг тус тусад нь хадгална («Мөр нэмэх» дараа шууд нүдэн
     * дээр бөглөдөг тул нэг талбар бүрд нэг хүсэлт очно).
     */
    public function update(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $leave), 403);

        $data = $request->validate([
            'scope' => ['sometimes', Rule::in(array_keys(self::SCOPES))],
            'org_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slip_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'signer' => ['sometimes', Rule::in(array_keys(Leave::SIGNERS))],
            'type' => ['sometimes', Rule::in(array_keys(Leave::TYPES))],
            'start_date' => ['sometimes', 'date'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $leave->fill($data);

        if (array_key_exists('start_date', $data) || array_key_exists('days', $data)) {
            $start = Carbon::parse($leave->start_date)->startOfDay();
            $days = (int) ($leave->days ?: 1);
            $leave->end_date = $start->copy()->addDays($days - 1)->toDateString();
        }

        $leave->save();

        return back(303)->with('success', 'Хадгаллаа.');
    }

    public function destroy(Request $request, Leave $leave): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $leave), 403);

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
