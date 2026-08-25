<?php

namespace App\Http\Controllers;

use App\Models\OrgEmployeePhone;
use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use App\Support\OrgEmployeePhoneDocxParser;
use App\Support\PhoneDirectoryDocxParser;
use App\Support\PhoneDirectoryDocxWriter;
use App\Support\TabularFileReader;
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

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['directory', 'staff'], true)) {
            $tab = 'directory';
        }

        $entries = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = $entries
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'category' => $rows->first()->category ?? 'baiguullaga',
                'rows' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'id' => $row->id,
                    'person_name' => $row->person_name,
                    'position' => $row->position,
                    'office_phone' => $row->office_phone,
                    'mobile_phone' => $row->mobile_phone,
                ])->values(),
            ])
            ->values();

        $staff = OrgEmployeePhone::query()
            ->orderBy('organization')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OrgEmployeePhone $row, int $i) => [
                'id' => $row->id,
                'no' => $i + 1,
                'organization' => $row->organization,
                'unit' => $row->unit,
                'position' => $row->position,
                'last_name' => $row->last_name,
                'first_name' => $row->first_name,
                'room' => $row->room,
                'work_phone' => $row->work_phone,
                'mobile_phone' => $row->mobile_phone,
                'email' => $row->email,
            ]);

        return Inertia::render('Modules/PhoneDirectory', [
            'tab' => $tab,
            'groups' => $groups,
            'total' => $entries->count(),
            'orgNames' => $entries->pluck('org_name')->unique()->values(),
            'categories' => PhoneDirectoryEntry::CATEGORIES,
            'staff' => $staff,
            'staffTotal' => $staff->count(),
            'staffOrganizations' => $staff->pluck('organization')->unique()->values(),
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
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

    public function exportStaff(Request $request, PhoneDirectoryDocxWriter $writer): HttpResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $staff = OrgEmployeePhone::query()
            ->orderBy('organization')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OrgEmployeePhone $row) => [
                'organization' => $row->organization,
                'unit' => $row->unit,
                'position' => $row->position,
                'last_name' => $row->last_name,
                'first_name' => $row->first_name,
                'room' => $row->room,
                'work_phone' => $row->work_phone,
                'mobile_phone' => $row->mobile_phone,
                'email' => $row->email,
            ])
            ->all();

        $tmp = tempnam(sys_get_temp_dir(), 'staff');
        $writer->writeStaff($staff, $tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        $fileName = 'АЗДТГ-н албан хаагчид '.now()->format('Y-m-d').'.docx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"org-employee-phones.docx\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
        ]);
    }

    public function importStaff(
        Request $request,
        OrgEmployeePhoneDocxParser $parser,
        TabularFileReader $reader,
    ): RedirectResponse {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (! in_array($extension, TabularFileReader::EXTENSIONS, true)) {
            return back()->withErrors([
                'staff_file' => 'Зөвхөн Word (.docx), Excel (.xlsx), PDF файл дэмжинэ.',
            ]);
        }

        try {
            $rows = $parser->fromRows(
                $reader->rows($request->file('file')->getRealPath(), $extension)
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['staff_file' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['staff_file' => 'Файлыг уншиж чадсангүй. Word (.docx), Excel (.xlsx) эсвэл текст агуулсан PDF оруулна уу.']);
        }

        if (! $rows) {
            return back()->withErrors([
                'staff_file' => 'Файлаас хүснэгт олдсонгүй. Баганын дараалал: № / Байгууллага / Нэгж / Албан тушаал / Овог / Нэр / Өрөө / Ажлын утас / Гар утас / И-мэйл хаяг. (Сканнердсан зурган PDF уншигдахгүй.)',
            ]);
        }

        $replace = $request->boolean('replace');

        DB::transaction(function () use ($rows, $replace) {
            if ($replace) {
                OrgEmployeePhone::query()->delete();
                $offset = 0;
            } else {
                $offset = (int) OrgEmployeePhone::query()->max('sort_order');
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                $now = now();

                OrgEmployeePhone::insert(array_map(fn (array $row) => [
                    'organization' => $row['organization'],
                    'unit' => $row['unit'],
                    'position' => $row['position'],
                    'last_name' => $row['last_name'],
                    'first_name' => $row['first_name'],
                    'room' => $row['room'],
                    'work_phone' => $row['work_phone'],
                    'mobile_phone' => $row['mobile_phone'],
                    'email' => $row['email'],
                    'sort_order' => $row['sort_order'] + $offset,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return redirect()
            ->route('phone-directory.index', ['tab' => 'staff'])
            ->with('success', count($rows).' мөр импортлолоо.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

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

        // Ангилал заагаагүй бол тухайн байгууллагын одоогийн ангиллыг, эсвэл нэрээр таамаглана.
        $data['category'] = $data['category']
            ?? PhoneDirectoryEntry::query()->where('org_name', $data['org_name'])->value('category')
            ?? PhoneDirectoryEntry::guessCategory($data['org_name']);

        $sibling = PhoneDirectoryEntry::query()->where('org_name', $data['org_name']);

        $data['org_order'] = (int) ((clone $sibling)->value('org_order')
            ?? (PhoneDirectoryEntry::query()->max('org_order') + 1));
        $data['sort_order'] = (int) (clone $sibling)->max('sort_order') + 1;

        PhoneDirectoryEntry::create($data);

        return redirect()
            ->route('phone-directory.index', ['tab' => 'directory'])
            ->with('success', 'Бүртгэл нэмэгдлээ.');
    }

    public function update(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

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

        $data['category'] = $data['category']
            ?? PhoneDirectoryEntry::query()->where('org_name', $data['org_name'])->value('category')
            ?? $entry->category
            ?? PhoneDirectoryEntry::guessCategory($data['org_name']);

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
            ->route('phone-directory.index', ['tab' => 'directory'])
            ->with('success', 'Бүртгэл шинэчлэгдлээ.');
    }

    /**
     * Байгууллагын ангиллыг (хэлтэс/агентлаг/сум/байгууллага) бүлгээр нь солино.
     */
    public function updateCategory(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(PhoneDirectoryEntry::CATEGORIES))],
        ]);

        PhoneDirectoryEntry::query()
            ->where('org_name', $data['org_name'])
            ->update(['category' => $data['category']]);

        return back(303)->with('success', $data['org_name'].' — ангилал шинэчлэгдлээ.');
    }

    public function destroy(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $entry->delete();

        return redirect()
            ->route('phone-directory.index', ['tab' => 'directory'])
            ->with('success', 'Устгалаа.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'organization' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:64'],
            'work_phone' => ['nullable', 'string', 'max:64'],
            'mobile_phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $data['sort_order'] = (int) OrgEmployeePhone::query()
            ->where('organization', $data['organization'])
            ->max('sort_order') + 1;

        OrgEmployeePhone::create($data);

        return redirect()
            ->route('phone-directory.index', ['tab' => 'staff'])
            ->with('success', 'Албан хаагчийн бүртгэл нэмэгдлээ.');
    }

    public function destroyStaff(Request $request, OrgEmployeePhone $staff): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $staff->delete();

        return redirect()
            ->route('phone-directory.index', ['tab' => 'staff'])
            ->with('success', 'Устгалаа.');
    }

    public function import(Request $request, PhoneDirectoryDocxParser $parser): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'docm'], true)) {
            return back()->withErrors(['file' => 'Зөвхөн .docx файл дэмжинэ. Word дээр «Save as → .docx» хийж оруулна уу.']);
        }

        try {
            $rows = $parser->parse($request->file('file')->getRealPath());
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
            ->route('phone-directory.index', ['tab' => 'directory'])
            ->with('success', count($rows).' мөр импортлолоо.');
    }
}
