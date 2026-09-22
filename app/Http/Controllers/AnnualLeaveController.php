<?php

namespace App\Http\Controllers;

use App\Models\AnnualLeave;
use App\Models\AuditLog;
use App\Models\PhoneDirectoryEntry;
use App\Support\DocxTableWriter;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use App\Support\PdfTableWriter;
use App\Support\XlsxTableWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ээлжийн амралтын бүртгэл — чөлөөний бүртгэлтэй ижил бүтэцтэй, гэвч
 * албан хаагчийн ажилласан жил, амралт олгох хоног, эзгүй хугацаанд
 * орлох албан тушаалтныг хөтөлнө.
 */
class AnnualLeaveController extends Controller
{
    private const MODULE = 'annual_leaves';

    private const SCOPES = [
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

        $query = AnnualLeave::query()->with(['user:id,name', 'department:id,name'])->latest('id');
        if ($scope !== 'all') {
            $query->where('scope', $scope);
        }
        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        $counts = AnnualLeave::query()
            ->selectRaw('scope, count(*) as aggregate')
            ->groupBy('scope')
            ->pluck('aggregate', 'scope');

        $tabs = [
            ['value' => 'all', 'label' => 'Нийт', 'count' => (int) $counts->sum()],
        ];
        foreach (self::SCOPES as $key => $label) {
            $tabs[] = ['value' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        $rows = $query->limit(300)->get()->map(fn (AnnualLeave $row) => $this->serialize($row));

        return Inertia::render('Modules/AnnualLeaves', [
            'activeScope' => $scope,
            'tabs' => $tabs,
            'rows' => $rows,
            'directory' => $this->directory(),
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
            'scopes' => self::SCOPES,
            'hasAuditLog' => true,
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

        $query = AnnualLeave::query()->latest('id');
        if ($scope !== 'all') {
            $query->where('scope', $scope);
        }
        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }

        $rowsQuery = $query->limit(2000)->get();
        $total = $rowsQuery->count();

        $headings = [
            'Д/д', 'Байгууллага', 'Албан тушаал', 'Овог, нэр', 'Улсад ажилласан жил',
            'Ээлжийн амралт олгох хоног', 'Эхлэх огноо', 'Дуусах огноо',
            'Орлох албан тушаал', 'Орлох овог, нэр', 'Орлох утасны дугаар',
        ];
        $widths = [500, 2000, 1800, 1800, 1200, 1400, 1100, 1100, 1600, 1600, 1300];
        $center = [0, 4, 5, 6, 7];

        $rows = $rowsQuery->values()->map(function (AnnualLeave $row, int $index) use ($total) {
            return [
                (string) ($total - $index),
                (string) ($row->org_name ?? ''),
                (string) ($row->position ?? ''),
                (string) ($row->person_name ?? ''),
                (string) ($row->work_years ?? ''),
                (string) ($row->entitled_days ?? ''),
                optional($row->start_date)?->format('Y-m-d') ?? '',
                optional($row->end_date)?->format('Y-m-d') ?? '',
                (string) ($row->substitute_position ?? ''),
                (string) ($row->substitute_name ?? ''),
                (string) ($row->substitute_phone ?? ''),
            ];
        })->all();

        $title = 'Ээлжийн амралтын бүртгэл'.($scope !== 'all' ? ' — '.self::SCOPES[$scope] : '');
        $tmp = tempnam(sys_get_temp_dir(), 'annual_leave_export_');

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
            'Content-Disposition' => "attachment; filename=\"annual-leaves.{$format}\"; filename*=UTF-8''".rawurlencode($fileName),
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
        ]);

        ModuleOwnScope::assertCanCreate($request->user(), self::MODULE, []);

        $scope = $data['scope'] ?? 'baiguullaga';

        $row = AnnualLeave::query()->create([
            'scope' => $scope,
            'user_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
        ]);

        $this->log($row, 'created');

        return redirect()
            ->route('annual-leaves.index', ['scope' => $scope])
            ->with('success', 'Ээлжийн амралтын бүртгэл хадгалагдлаа.');
    }

    /**
     * Хүснэгтийн нүдийг тус тусад нь хадгална («Мөр нэмэх» дараа шууд нүдэн
     * дээр бөглөдөг тул нэг талбар бүрд нэг хүсэлт очно).
     */
    public function update(Request $request, AnnualLeave $annualLeave): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $annualLeave), 403);

        $data = $request->validate([
            'scope' => ['sometimes', Rule::in(array_keys(self::SCOPES))],
            'person_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'work_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'entitled_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'substitute_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'substitute_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'substitute_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        // Овог нэрийг сонгоход албан тушаал, байгууллага нь дагаж бөглөгдөнө.
        if (array_key_exists('person_name', $data)) {
            $data['position'] = filled($data['person_name'])
                ? PhoneDirectoryEntry::positionFor((string) $data['person_name'])
                : null;
            $data['org_name'] = filled($data['person_name'])
                ? PhoneDirectoryEntry::orgFor((string) $data['person_name'])
                : null;
        }

        // Орлох хүний нэрийг сонгоход түүний тушаал, утас нь дагаж бөглөгдөнө.
        if (array_key_exists('substitute_name', $data)) {
            if (filled($data['substitute_name'])) {
                $data['substitute_position'] = PhoneDirectoryEntry::positionFor((string) $data['substitute_name']);
                $data['substitute_phone'] = PhoneDirectoryEntry::phoneFor((string) $data['substitute_name']);
            } else {
                $data['substitute_position'] = null;
                $data['substitute_phone'] = null;
            }
        }

        // Улсад ажилласан жилийг бөглөхөд амралт олгох хоног стандартаар тооцогдоно.
        if (array_key_exists('work_years', $data) && ! array_key_exists('entitled_days', $data)) {
            $data['entitled_days'] = AnnualLeave::entitledDaysFor($data['work_years']);
        }

        // Эхлэх огноо, эсвэл олгох хоног өөрчлөгдвөл дуусах огноог дагуулна.
        if (array_key_exists('start_date', $data) || array_key_exists('entitled_days', $data)) {
            $startValue = array_key_exists('start_date', $data) ? $data['start_date'] : $annualLeave->start_date;
            $daysValue = array_key_exists('entitled_days', $data) ? $data['entitled_days'] : $annualLeave->entitled_days;

            $data['end_date'] = ($startValue && $daysValue)
                ? Carbon::parse($startValue)->addDays((int) $daysValue - 1)->toDateString()
                : null;
        }

        $changes = $this->changedFields($annualLeave, $data);

        $annualLeave->fill($data);
        $annualLeave->save();

        if ($changes !== []) {
            $this->log($annualLeave, 'updated', $changes);
        }

        return back(303)->with('success', 'Хадгаллаа.');
    }

    public function destroy(Request $request, AnnualLeave $annualLeave): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $annualLeave), 403);

        $scope = $annualLeave->scope ?: 'baiguullaga';
        $this->log($annualLeave, 'deleted');
        $annualLeave->delete();

        return redirect()
            ->route('annual-leaves.index', ['scope' => $scope])
            ->with('success', 'Устгалаа.');
    }

    /**
     * Өөрчлөлтийн түүх — хэн, хэзээ, юуг сольсныг харуулна.
     */
    public function logs(Request $request): JsonResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $scope = (string) $request->query('scope', 'all');

        $rows = AuditLog::query()
            ->with('user:id,name')
            ->where('model_type', self::MODULE)
            ->when(
                $scope !== 'all' && array_key_exists($scope, self::SCOPES),
                fn ($query) => $query->where('scope', $scope),
            )
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'label' => $log->label,
                'summary' => $log->summary,
                'changes' => $log->changes,
                'scope' => self::SCOPES[$log->scope] ?? $log->scope,
                'user' => $log->user?->name ?? 'Систем',
                'at' => optional($log->created_at)?->format('Y-m-d H:i'),
            ]);

        return response()->json(['rows' => $rows]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AnnualLeave $row): array
    {
        return [
            'id' => $row->id,
            'scope' => $row->scope,
            'scope_label' => self::SCOPES[$row->scope] ?? $row->scope,
            'org_name' => $row->org_name,
            'position' => $row->position,
            'person_name' => $row->person_name,
            'work_years' => $row->work_years,
            'entitled_days' => $row->entitled_days,
            'start_date' => optional($row->start_date)?->format('Y-m-d'),
            'end_date' => optional($row->end_date)?->format('Y-m-d'),
            'substitute_position' => $row->substitute_position,
            'substitute_name' => $row->substitute_name,
            'substitute_phone' => $row->substitute_phone,
        ];
    }

    /**
     * Өөрчлөлтийн лог үлдээнэ.
     *
     * @param  array<string, mixed>|null  $changes
     */
    private function log(AnnualLeave $row, string $action, ?array $changes = null): void
    {
        AuditLog::record(
            modelType: self::MODULE,
            modelId: $row->getKey(),
            action: $action,
            scope: $row->scope ?: null,
            label: $this->rowLabel($row),
            changes: $changes,
        );
    }

    /** Логт харагдах богино нэр. */
    private function rowLabel(AnnualLeave $row): string
    {
        $name = trim((string) $row->person_name);

        return $name !== '' ? $name : '#'.$row->getKey();
    }

    /**
     * Өөрчлөгдсөн талбаруудыг хуучин/шинэ утгаар нь тэмдэглэнэ.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: string, to: string}>
     */
    private function changedFields(AnnualLeave $row, array $data): array
    {
        $labels = [
            'scope' => 'Хамрах хүрээ',
            'org_name' => 'Байгууллага',
            'position' => 'Албан тушаал',
            'person_name' => 'Овог, нэр',
            'work_years' => 'Улсад ажилласан жил',
            'entitled_days' => 'Ээлжийн амралт олгох хоног',
            'start_date' => 'Эхлэх огноо',
            'end_date' => 'Дуусах огноо',
            'substitute_position' => 'Орлох албан тушаал',
            'substitute_name' => 'Орлох овог, нэр',
            'substitute_phone' => 'Орлох утасны дугаар',
        ];
        $changes = [];

        foreach ($data as $name => $new) {
            $old = $row->getOriginal($name);

            $oldText = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (string) ($old ?? '');
            $newText = $new instanceof \DateTimeInterface ? $new->format('Y-m-d') : (string) ($new ?? '');

            if ($oldText === $newText) {
                continue;
            }

            $changes[$labels[$name] ?? $name] = ['from' => $oldText, 'to' => $newText];
        }

        return $changes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directory(): array
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
